<?php

namespace App\Services;

use App\Events\EventoRegistrado;
use App\Exceptions\SinTurnoActivoException;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio del registro y consulta de eventos.
 *
 * SINGLE RESPONSIBILITY: sólo eventos. Correcciones -> ErrataService.
 * OBSERVER: NO notifica de forma directa. Al persistir un Evento dispara el
 * evento de dominio EventoRegistrado y un Listener en cola se encarga de la
 * notificación (si WhatsApp falla, el evento igual queda guardado).
 *
 * REGLA DE NEGOCIO: los eventos son INMUTABLES. Este service NO expone update().
 */
class EventoService
{
    /**
     * Columnas por las que se puede ordenar la consulta (US-009).
     */
    public const ORDENES = ['id', 'fecha_hora', 'tipo', 'punto', 'autor'];

    /**
     * US-003: registra un evento asociado al turno activo y a su autor.
     *
     * @param  array{tipo_evento_id: int, fecha: string, hora: string, punto_monitoreo_id?: int|null, timestamp_video?: string, descripcion?: string|null}  $datos
     *
     * @throws SinTurnoActivoException
     */
    public function registrar(array $datos, User $autor): Evento
    {
        $evento = DB::transaction(function () use ($datos, $autor) {
            // Regla de negocio 5 + US-025: el evento se liga al turno abierto DEL
            // supervisor que lo carga. Si no tiene uno abierto (o lo cerró), no
            // se puede registrar.
            $turno = Turno::abierto()
                ->deSupervisor($autor->id)
                ->latest('id')
                ->lockForUpdate()
                ->first()
                ?? throw new SinTurnoActivoException;

            $puntoMonitoreoId = $datos['punto_monitoreo_id'] ?? null;

            return Evento::create([
                'tipo_evento_id' => $datos['tipo_evento_id'],
                'punto_monitoreo_id' => $puntoMonitoreoId,
                'turno_id' => $turno->id,
                'usuario_id' => $autor->id,
                'fecha_hora' => Carbon::createFromFormat('Y-m-d H:i:s', "{$datos['fecha']} {$datos['hora']}"),
                'timestamp_video' => $puntoMonitoreoId ? $datos['timestamp_video'] : null,
                'descripcion' => $datos['descripcion'] ?? null,
            ]);
        });

        // Fuera de la transacción: el listener en cola sólo ve eventos ya confirmados.
        EventoRegistrado::dispatch($evento);

        return $evento;
    }

    /**
     * @return Collection<int, TipoEvento>
     */
    public function tiposEvento(): Collection
    {
        return TipoEvento::orderBy('nombre')
            ->get(['id', 'nombre', 'categoria', 'requiere_ubicacion']);
    }

    /**
     * @return Collection<int, PuntoMonitoreo>
     */
    public function puntosMonitoreo(): Collection
    {
        return PuntoMonitoreo::orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'jurisdiccion', 'latitud', 'longitud']);
    }

    public function turnoActivoDe(User $supervisor): ?Turno
    {
        return Turno::activoDe($supervisor->id)?->load('supervisor:id,name');
    }

    /**
     * US-010: evento con todo lo necesario para mostrar su detalle.
     */
    public function detalle(Evento $evento): Evento
    {
        return $evento->load([
            'tipoEvento:id,nombre,categoria,requiere_ubicacion,es_cuantificable',
            'puntoMonitoreo:id,codigo,nombre,jurisdiccion,latitud,longitud',
            'usuario:id,name',
            'turno:id,fecha,hora_inicio,supervisor_id',
            'notificaciones.grupoInteresado:id,nombre',
            'erratas.autor:id,name',
        ]);
    }

    /**
     * CU04: eventos registrados, del más reciente al más antiguo.
     * `hasta` incluye el día completo.
     *
     * US-008: todos los filtros se combinan (AND).
     * // TODO: filtro por zona/cuadrante cuando los PMs tengan ese dato (US-024).
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Evento>
     */
    public function buscar(array $filtros, User $consultante, int $porPagina = 20): LengthAwarePaginator
    {
        return $this->consulta($filtros, $consultante)
            ->withCount('erratas')
            ->with([
                'notificaciones:id,evento_id,grupo_interesado_id,estado,intentos,error',
                'notificaciones.grupoInteresado:id,nombre',
            ])
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * US-013: todos los eventos que coinciden con los filtros, sin límite ni
     * paginación, en el mismo orden que la consulta.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Evento>
     */
    public function paraExportar(array $filtros, User $consultante): Collection
    {
        return $this->consulta($filtros, $consultante)->get();
    }

    /**
     * Consulta filtrada y ordenada, respetando el alcance del usuario.
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<Evento>
     */
    private function consulta(array $filtros, User $consultante): Builder
    {
        return $this->visiblesPara($consultante)
            ->with([
                'tipoEvento:id,nombre,categoria,es_cuantificable',
                'puntoMonitoreo:id,codigo,nombre,jurisdiccion',
                'usuario:id,name',
            ])
            ->when($filtros['tipo_evento_id'] ?? null, fn (Builder $q, $id) => $q->where('tipo_evento_id', $id))
            ->when($filtros['categorias'] ?? null, fn (Builder $q, array $categorias) => $q
                ->whereHas('tipoEvento', fn (Builder $t) => $t->whereIn('categoria', $categorias)))
            ->when($filtros['punto_monitoreo_id'] ?? null, fn (Builder $q, $id) => $q->where('punto_monitoreo_id', $id))
            ->when($filtros['jurisdiccion'] ?? null, fn (Builder $q, $jurisdiccion) => $q
                ->whereHas('puntoMonitoreo', fn (Builder $p) => $p->where('jurisdiccion', $jurisdiccion)))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $desde) => $q
                ->where('fecha_hora', '>=', Carbon::parse($desde)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $hasta) => $q
                ->where('fecha_hora', '<', Carbon::parse($hasta)->addDay()->startOfDay()))
            ->tap(fn (Builder $q) => $this->filtrarFranjaHoraria($q, $filtros['hora_desde'] ?? null, $filtros['hora_hasta'] ?? null))
            // Sin distinguir mayúsculas; % y _ del usuario se buscan literal.
            ->when($filtros['texto'] ?? null, fn (Builder $q, $texto) => $q
                ->whereRaw("lower(descripcion) like ? escape '\\'", [
                    '%'.addcslashes(mb_strtolower($texto), '%_\\').'%',
                ]))
            ->tap(fn (Builder $q) => $this->ordenar($q, $filtros['orden'] ?? 'fecha_hora', $filtros['direccion'] ?? 'desc'));
    }

    /**
     * US-008: franja horaria del hecho, independiente del día. Si `desde` es
     * mayor que `hasta` la franja cruza la medianoche (p. ej. 22:00 a 06:00).
     */
    private function filtrarFranjaHoraria(Builder $query, ?string $desde, ?string $hasta): void
    {
        // HH:MM -> se incluye el minuto completo de `hasta`.
        $desde = $desde ? substr($desde, 0, 5).':00' : null;
        $hasta = $hasta ? substr($hasta, 0, 5).':59' : null;

        if ($desde && $hasta && $desde > $hasta) {
            $query->where(fn (Builder $q) => $q
                ->whereTime('fecha_hora', '>=', $desde)
                ->orWhereTime('fecha_hora', '<=', $hasta));

            return;
        }

        $query
            ->when($desde, fn (Builder $q) => $q->whereTime('fecha_hora', '>=', $desde))
            ->when($hasta, fn (Builder $q) => $q->whereTime('fecha_hora', '<=', $hasta));
    }

    /**
     * US-009: orden por columna. Las columnas de relaciones se ordenan con una
     * subconsulta para no duplicar filas con joins.
     */
    private function ordenar(Builder $query, string $orden, string $direccion): void
    {
        $direccion = $direccion === 'asc' ? 'asc' : 'desc';

        match ($orden) {
            'id' => $query->orderBy('id', $direccion),
            'tipo' => $query->orderBy(
                TipoEvento::select('nombre')->whereColumn('tipos_evento.id', 'eventos.tipo_evento_id'),
                $direccion,
            ),
            'punto' => $query->orderBy(
                PuntoMonitoreo::select('codigo')->whereColumn('puntos_monitoreo.id', 'eventos.punto_monitoreo_id'),
                $direccion,
            ),
            'autor' => $query->orderBy(
                User::select('name')->whereColumn('users.id', 'eventos.usuario_id'),
                $direccion,
            ),
            default => $query->orderBy('fecha_hora', $direccion),
        };

        // Desempate estable para la paginación.
        $query->orderBy('id', $direccion);
    }

    /**
     * Alcance de la consulta: con `eventos.consultar_historico` (Administrativo,
     * Administrador de sistema) se ve todo; sin él (Supervisor) solo los
     * eventos del turno que tiene abierto, y nada si no tiene turno abierto.
     *
     * @return Builder<Evento>
     */
    public function visiblesPara(User $consultante): Builder
    {
        $query = Evento::query();

        if ($consultante->can('eventos.consultar_historico')) {
            return $query;
        }

        $turno = Turno::activoDe($consultante->id);

        return $turno
            ? $query->where('turno_id', $turno->id)
            : $query->whereRaw('1 = 0');
    }

    public function puedeVer(User $consultante, Evento $evento): bool
    {
        return $this->visiblesPara($consultante)->whereKey($evento->id)->exists();
    }
}
