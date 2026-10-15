<?php

namespace App\Http\Controllers;

use App\Exceptions\SinTurnoActivoException;
use App\Http\Requests\BuscarEventosRequest;
use App\Http\Requests\StoreEventoRequest;
use App\Models\AuditoriaAcceso;
use App\Models\Errata;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\ErrataService;
use App\Services\EventoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web (Inertia). Controller FINO: sólo orquesta, la lógica vive en EventoService.
 * REGLA DE NEGOCIO: no hay update() -> los eventos son inmutables (usar Errata).
 */
class EventoController extends Controller
{
    public function __construct(
        private readonly EventoService $eventos,
        private readonly AuditoriaAccesoService $auditoria,
        private readonly ErrataService $erratasLegibles,
    ) {}

    /**
     * CU04: consulta de eventos registrados con filtros (eventos.consultar).
     */
    public function index(BuscarEventosRequest $request): Response
    {
        $filtros = $request->validated();

        $eventos = $this->eventos->buscar($filtros, $request->user())->through(fn (Evento $e) => [
            'id' => $e->id,
            'fecha_hora' => $e->fecha_hora->format('d/m/Y H:i:s'),
            'tipo' => $e->tipoEvento->nombre,
            'categoria' => TipoEvento::CATEGORIAS[$e->tipoEvento->categoria] ?? $e->tipoEvento->categoria,
            'punto' => $e->puntoMonitoreo
                ? "{$e->puntoMonitoreo->codigo} — {$e->puntoMonitoreo->nombre}"
                : null,
            'timestamp_video' => $e->timestamp_video,
            'descripcion' => $e->descripcion,
            'autor' => $e->usuario?->name,
            'turno_id' => $e->turno_id,
            'notificaciones' => $this->notificaciones($e),
            'corregido' => $e->erratas_count > 0,
        ]);

        return Inertia::render('Eventos/Index', [
            'eventos' => $eventos,
            'filtros' => (object) $filtros,
            'tipos' => $this->eventos->tiposEvento(),
            'categorias' => TipoEvento::CATEGORIAS,
            'puntos' => $this->eventos->puntosMonitoreo(),
            'jurisdicciones' => PuntoMonitoreo::JURISDICCIONES,
            // Supervisor: la consulta se limita a su turno abierto (null = ve todo).
            'alcanceTurno' => $request->user()->can('eventos.consultar_historico')
                ? null
                : ['id' => $this->eventos->turnoActivoDe($request->user())?->id],
        ]);
    }

    /**
     * US-003: formulario de registro de evento (Supervisor).
     */
    public function create(Request $request): Response
    {
        $turno = $this->eventos->turnoActivoDe($request->user());
        $puntos = $this->eventos->puntosMonitoreo();

        // Registro desde un PM del mapa: ?punto_monitoreo_id=X precarga la ubicación.
        // Un id inexistente se ignora (el form arranca sin PM).
        $puntoInicial = $puntos->firstWhere('id', $request->integer('punto_monitoreo_id'))?->id;

        return Inertia::render('Eventos/Create', [
            'tipos' => $this->eventos->tiposEvento(),
            'categorias' => TipoEvento::CATEGORIAS,
            'puntos' => $puntos,
            'puntoInicial' => $puntoInicial,
            'jurisdicciones' => PuntoMonitoreo::JURISDICCIONES,
            'turnoActivo' => $turno ? [
                'id' => $turno->id,
                'fecha' => $turno->fecha->format('d/m/Y'),
                'hora_inicio' => substr($turno->hora_inicio, 0, 5),
                'supervisor' => $turno->supervisor?->name,
            ] : null,
        ]);
    }

    public function store(StoreEventoRequest $request): RedirectResponse
    {
        try {
            $evento = $this->eventos->registrar($request->validated(), $request->user());
        } catch (SinTurnoActivoException $e) {
            throw ValidationException::withMessages(['turno' => $e->getMessage()]);
        }

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_REGISTRO_EVENTO,
            $request->user(),
            "Evento #{$evento->id} registrado ({$evento->tipoEvento->nombre}) en el turno #{$evento->turno_id}.",
        );

        return redirect()->route('eventos.create')
            ->with('success', "Evento #{$evento->id} registrado correctamente.");
    }

    /**
     * US-010: detalle completo de un evento (eventos.consultar).
     */
    public function show(Request $request, Evento $evento): Response
    {
        abort_unless($this->eventos->puedeVer($request->user(), $evento), 403);

        $evento = $this->eventos->detalle($evento);

        // Se muestran los valores VIGENTES (original + fe de erratas); el
        // registro original queda en el historial de correcciones.
        $vigentes = $evento->valoresVigentes();
        $tipo = TipoEvento::find($vigentes['tipo_evento_id']);
        $punto = $vigentes['punto_monitoreo_id'] ? PuntoMonitoreo::find($vigentes['punto_monitoreo_id']) : null;
        $fechaHora = Carbon::parse($vigentes['fecha_hora']);

        return Inertia::render('Eventos/Show', [
            'puedeCorregir' => $request->user()->can('erratas.crear'),
            'evento' => [
                'id' => $evento->id,
                'tipo' => $tipo->nombre,
                'categoria' => TipoEvento::CATEGORIAS[$tipo->categoria] ?? $tipo->categoria,
                'informativo' => $tipo->categoria === 'informativo',
                'cuantificable' => $tipo->es_cuantificable,
                'fecha' => $fechaHora->format('d/m/Y'),
                'hora' => $fechaHora->format('H:i:s'),
                'descripcion' => $vigentes['descripcion'],
                'punto' => $punto ? [
                    'codigo' => $punto->codigo,
                    'nombre' => $punto->nombre,
                    'jurisdiccion' => PuntoMonitoreo::JURISDICCIONES[$punto->jurisdiccion] ?? $punto->jurisdiccion,
                    'latitud' => $punto->latitud,
                    'longitud' => $punto->longitud,
                ] : null,
                'timestamp_video' => $vigentes['timestamp_video'],
                'erratas' => $evento->erratas->map(fn (Errata $e) => [
                    'id' => $e->id,
                    'campo' => Errata::CAMPOS[$e->campo_corregido] ?? $e->campo_corregido,
                    'anterior' => $this->erratasLegibles->legible($e->campo_corregido, $e->valor_anterior),
                    'nuevo' => $this->erratasLegibles->legible($e->campo_corregido, $e->valor_nuevo),
                    'motivo' => $e->motivo,
                    'autor' => $e->autor?->name,
                    'fecha' => $e->created_at?->format('d/m/Y H:i:s'),
                ]),
                'autor' => $evento->usuario?->name,
                'registrado' => $evento->created_at?->format('d/m/Y H:i:s'),
                'turno' => $evento->turno ? [
                    'id' => $evento->turno->id,
                    'fecha' => $evento->turno->fecha->format('d/m/Y'),
                    'hora_inicio' => substr((string) $evento->turno->hora_inicio, 0, 5),
                ] : null,
                'notificaciones' => $this->notificaciones($evento),
            ],
        ]);
    }

    /**
     * US-006/US-007: resultado del envío a cada grupo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function notificaciones(Evento $evento): Collection
    {
        return $evento->notificaciones->map(fn (Notificacion $n) => [
            'id' => $n->id,
            'grupo' => $n->grupoInteresado?->nombre,
            'estado' => $n->estado,
            'intentos' => $n->intentos,
            'error' => $n->error,
        ]);
    }
}
