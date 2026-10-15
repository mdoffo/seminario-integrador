<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use App\Services\Auth\AuditoriaAccesoService;
use App\Services\Auth\AuditoriaConsultaService;
use App\Services\ExportacionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Log de auditoría (US-026 / US-027) — vista de SOLO LECTURA para el
 * Administrador de sistema (gateada por permission:usuarios.gestionar).
 * Filtros, orden, exportación a Excel; el acceso queda auditado.
 */
class AuditoriaController extends Controller
{
    public function __construct(
        private readonly AuditoriaConsultaService $consulta,
        private readonly AuditoriaAccesoService $auditoria,
        private readonly ExportacionService $exportacion,
    ) {}

    public function index(Request $request): Response
    {
        $filtros = $this->validar($request);
        $this->consulta->registrarAcceso($request->user());

        $registros = $this->consulta->consulta($filtros)
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditoriaAcceso $a) => $this->consulta->fila($a));

        return Inertia::render('Usuarios/Auditoria', [
            'registros' => $registros,
            'filtros' => (object) $filtros,
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
            'acciones' => AuditoriaAcceso::ACCIONES,
        ]);
    }

    public function exportar(Request $request): HttpResponse
    {
        $filtros = $this->validar($request);

        $filas = $this->consulta->consulta($filtros)->get()
            ->map(fn (AuditoriaAcceso $a) => array_values(array_intersect_key(
                $this->consulta->fila($a),
                array_flip(['fecha_hora', 'usuario', 'email', 'rol', 'accion', 'ip', 'descripcion']),
            )))
            ->all();

        $this->auditoria->registrarAccion(
            AuditoriaAcceso::EVENTO_EXPORTACION,
            $request->user(),
            mb_substr('Exportación del log de auditoría ('.count($filas).' registros).', 0, 500),
        );

        $contenido = $this->exportacion->exportar('xlsx', [
            'titulo' => 'Auditoría',
            'columnas' => ['Fecha y hora', 'Usuario', 'Email', 'Rol', 'Acción', 'IP', 'Detalle'],
            'filas' => $filas,
        ]);

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Auditoria_'.now()->format('d-m-Y_His').'.xlsx"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'usuario_id' => ['nullable', 'integer', 'exists:users,id'],
            'accion' => ['nullable', Rule::in(array_keys(AuditoriaAcceso::ACCIONES))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'objeto' => ['nullable', 'string', 'max:60'],
            'orden' => ['nullable', Rule::in(AuditoriaConsultaService::ORDENES)],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
    }
}
