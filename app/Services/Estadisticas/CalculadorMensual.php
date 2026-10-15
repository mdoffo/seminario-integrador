<?php

namespace App\Services\Estadisticas;

use Illuminate\Support\Carbon;

/**
 * US-014: estadística de un mes; la evolución se agrupa semana a semana
 * (días 1–7, 8–14, 15–21, 22–28 y 29–fin de mes).
 */
class CalculadorMensual extends CalculadorEstadistica
{
    protected function tramos(Carbon $desde, Carbon $hasta): array
    {
        $tramos = [];
        $inicio = $desde->copy();
        $numero = 1;

        while ($inicio->lt($hasta)) {
            $fin = $inicio->copy()->addDays(7)->min($hasta);

            $tramos[] = [
                "semana_{$numero}",
                "Sem {$numero} ({$inicio->day}–{$fin->copy()->subDay()->day})",
                $inicio,
                $fin,
            ];

            $inicio = $fin;
            $numero++;
        }

        return $tramos;
    }
}
