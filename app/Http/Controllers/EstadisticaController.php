<?php

namespace App\Http\Controllers;

use App\Services\EstadisticaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-014: estadísticas mensuales y anuales (permiso estadisticas.ver).
 * Controller FINO: el cálculo vive en EstadisticaService.
 */
class EstadisticaController extends Controller
{
    public function __construct(private readonly EstadisticaService $estadisticas) {}

    public function index(Request $request): Response
    {
        $datos = $request->validate([
            'periodo' => ['nullable', 'in:mensual,anual'],
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $periodo = $datos['periodo'] ?? 'mensual';
        $anio = (int) ($datos['anio'] ?? now()->year);
        $mes = (int) ($datos['mes'] ?? now()->month);

        return Inertia::render('Estadisticas/Index', [
            'filtros' => ['periodo' => $periodo, 'anio' => $anio, 'mes' => $mes],
            'estadisticas' => $periodo === 'anual'
                ? $this->estadisticas->anual($anio)
                : $this->estadisticas->mensual($anio, $mes),
        ]);
    }
}
