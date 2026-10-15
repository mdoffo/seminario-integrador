<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\EstadisticaService;
use Illuminate\Http\JsonResponse;

/**
 * API PÚBLICA v1, sólo lectura: agregados para Gobierno Abierto (US-014).
 * Mismos números que la pantalla de Estadísticas.
 */
class EstadisticaApiController extends Controller
{
    public function __construct(
        private EstadisticaService $estadisticas,
    ) {}

    public function mensual(int $anio, int $mes): JsonResponse
    {
        abort_unless($anio >= 2000 && $anio <= 2100 && $mes >= 1 && $mes <= 12, 404);

        return response()->json(['data' => $this->estadisticas->mensual($anio, $mes)]);
    }

    public function anual(int $anio): JsonResponse
    {
        abort_unless($anio >= 2000 && $anio <= 2100, 404);

        return response()->json(['data' => $this->estadisticas->anual($anio)]);
    }
}
