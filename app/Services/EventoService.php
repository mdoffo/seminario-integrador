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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
     * Tipos para selects; `categoria` es el código de su GrupoEvento.
     *
     * @return Collection<int, array{id: int, nombre: string, categoria: string, requiere_ubicacion: bool}>
     */
    public function tiposEvento(): Collection
    {
        return TipoEvento::with('grupoEvento:id,codigo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'grupo_evento_id', 'requiere_ubicacion'])
            ->map(fn (TipoEvento $t) => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'categoria' => $t->grupoEvento->codigo,
                'requiere_ubicacion' => $t->requiere_ubicacion,
            ])
            ->toBase();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function puntosMonitoreo(): Collection
    {
        return PuntoMonitoreo::paraMapa();
    }

    public function turnoActivoDe(User $supervisor): ?Turno
    {
        return Turno::activoDe($supervisor->id)?->load('supervisor:id,name');
    }

    /**
     * CU04: eventos registrados, del más reciente al más antiguo.
     * `hasta` incluye el día completo.
     *
     * // TODO: confirmar con el equipo si se filtra también por franja horaria.
     *
     * @param  array{tipo_evento_id?: int|string|null, categoria?: string|null, punto_monitoreo_id?: int|string|null, desde?: string|null, hasta?: string|null}  $filtros
     * @return LengthAwarePaginator<int, Evento>
     */
    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        return Evento::query()
            ->with(['tipoEvento:id,nombre,grupo_evento_id', 'tipoEvento.grupoEvento:id,nombre', 'puntoMonitoreo:id,codigo,nombre', 'usuario:id,name'])
            ->when($filtros['tipo_evento_id'] ?? null, fn (Builder $q, $id) => $q->where('tipo_evento_id', $id))
            ->when($filtros['categoria'] ?? null, fn (Builder $q, $categoria) => $q
                ->whereHas('tipoEvento.grupoEvento', fn (Builder $g) => $g->where('codigo', $categoria)))
            ->when($filtros['punto_monitoreo_id'] ?? null, fn (Builder $q, $id) => $q->where('punto_monitoreo_id', $id))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $desde) => $q
                ->where('fecha_hora', '>=', Carbon::parse($desde)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $hasta) => $q
                ->where('fecha_hora', '<', Carbon::parse($hasta)->addDay()->startOfDay()))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }
}
