<?php

namespace App\Services;

use App\Contracts\EstadisticaCalculadorInterface;
use App\Services\Estadisticas\CalculadorAnual;
use App\Services\Estadisticas\CalculadorMensual;
use Illuminate\Support\Carbon;

/**
 * Orquesta el cálculo de agregados (mensual / anual) por tipo y ubicación (US-014).
 *
 * SINGLE RESPONSIBILITY: estadísticas.
 * STRATEGY + DEPENDENCY INVERSION: delega el cómo del cálculo en un
 * EstadisticaCalculadorInterface concreto, elegido según el período.
 * REGLA DE NEGOCIO: excluir TipoEvento con es_cuantificable = false.
 */
class EstadisticaService
{
    public function mensual(int $anio, int $mes): array
    {
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();

        return $this->calcular(
            new CalculadorMensual,
            $desde,
            $desde->copy()->addMonth(),
            ucfirst($desde->translatedFormat('F Y')),
        );
    }

    public function anual(int $anio): array
    {
        $desde = Carbon::create($anio, 1, 1)->startOfDay();

        return $this->calcular(new CalculadorAnual, $desde, $desde->copy()->addYear(), (string) $anio);
    }

    /**
     * @return array<string, mixed>
     */
    private function calcular(EstadisticaCalculadorInterface $calculador, Carbon $desde, Carbon $hasta, string $titulo): array
    {
        return [
            'periodo' => [
                'titulo' => $titulo,
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->copy()->subDay()->toDateString(),
            ],
            ...$calculador->calcular($desde, $hasta),
        ];
    }
}
