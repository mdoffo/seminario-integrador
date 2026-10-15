<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarEventosRequest;
use App\Models\AuditoriaAcceso;
use App\Models\Turno;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\ReporteService;
use DomainException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * US-012 (PDF por turno) y US-013 (planilla de eventos). Permiso
 * reportes.exportar (Administrativo y Administrador de sistema).
 * Controller fino: los datos los arma ReporteService y el archivo lo genera
 * ExportacionService (ExportadorFactory). Cada descarga queda auditada (US-026).
 */
class ReporteController extends Controller
{
    public function __construct(
        private readonly ReporteService $reportes,
        private readonly AuditoriaAccesoService $auditoria,
    ) {}

    /**
     * Turnos cerrados para elegir cuál imprimir.
     */
    public function index(Request $request): Response
    {
        $fecha = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']])['fecha'] ?? null;

        $turnos = Turno::query()
            ->whereNotNull('hora_fin')
            ->when($fecha, fn ($q) => $q->whereDate('fecha', $fecha))
            ->with('supervisor:id,name')
            ->withCount('eventos')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Turno $t) => [
                'id' => $t->id,
                'fecha' => $t->fecha->format('d/m/Y'),
                'inicio' => substr((string) $t->hora_inicio, 0, 5),
                'fin' => substr((string) $t->hora_fin, 0, 5),
                'fecha_fin' => $t->fecha_fin?->format('d/m/Y'),
                'supervisor' => $t->supervisor?->name,
                'eventos' => $t->eventos_count,
            ]);

        return Inertia::render('Reportes/Index', [
            'turnos' => $turnos,
            'filtros' => ['fecha' => $fecha],
        ]);
    }

    public function turnoPdf(Request $request, Turno $turno): HttpResponse
    {
        try {
            $reporte = $this->reportes->pdfDeTurno($turno, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_REPORTE_PDF,
            $request->user(),
            "Reporte PDF del turno #{$turno->id} generado.",
        );

        return response($reporte['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$reporte['nombre'].'"',
        ]);
    }

    public function eventosXlsx(BuscarEventosRequest $request): HttpResponse
    {
        $filtros = $request->validated();
        $planilla = $this->reportes->planillaDeEventos($filtros, $request->user());

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_EXPORTACION,
            $request->user(),
            mb_substr("Exportación .xlsx de {$planilla['cantidad']} evento(s). Filtros: "
                .($filtros ? json_encode($filtros, JSON_UNESCAPED_UNICODE) : 'ninguno'), 0, 500),
        );

        return response($planilla['contenido'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$planilla['nombre'].'"',
            'X-Cantidad-Eventos' => (string) $planilla['cantidad'],
        ]);
    }
}
