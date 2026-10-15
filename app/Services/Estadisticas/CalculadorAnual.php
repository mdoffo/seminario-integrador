<?php

namespace App\Services\Estadisticas;

use Illuminate\Support\Carbon;

/**
 * US-014: estadística de un año; la evolución se agrupa mes a mes.
 */
class CalculadorAnual extends CalculadorEstadistica
{
    protected function tramos(Carbon $desde, Carbon $hasta): array
    {
        $tramos = [];

        for ($inicio = $desde->copy(); $inicio->lt($hasta); $inicio = $inicio->copy()->addMonth()) {
            $tramos[] = [
                $inicio->format('Y-m'),
                ucfirst($inicio->translatedFormat('M')),
                $inicio,
                $inicio->copy()->addMonth(),
            ];
        }

        return $tramos;
    }
}
