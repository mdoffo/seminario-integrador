<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\GrupoEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Dashboards de análisis (US-015, US-018, US-019, US-020).
 *
 * Mismos criterios que las estadísticas (US-014): solo tipos cuantificables
 * (RN4); el ranking por ubicación excluye los eventos sin PM. Se lee una vez
 * el período y se agrega en PHP, sin funciones de fecha propias de un motor.
 */
class AnalisisService
{
    public const GRANULARIDADES = ['dia', 'semana', 'mes'];

    public const PERIODOS = ['mes' => 30, 'trimestre' => 90, 'anio' => 365];

    public const TOP_RANKING = 10;

    /**
     * Rango efectivo según el preset o las fechas personalizadas.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public function rango(array $filtros): array
    {
        $periodo = $filtros['periodo'] ?? 'mes';

        if ($periodo === 'personalizado' && isset($filtros['desde'], $filtros['hasta'])) {
            return [
                'desde' => Carbon::parse($filtros['desde'])->startOfDay(),
                'hasta' => Carbon::parse($filtros['hasta'])->endOfDay(),
            ];
        }

        $dias = self::PERIODOS[$periodo] ?? self::PERIODOS['mes'];

        return [
            'desde' => now()->subDays($dias - 1)->startOfDay(),
            'hasta' => now()->endOfDay(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function calcular(array $filtros): array
    {
        ['desde' => $desde, 'hasta' => $hasta] = $this->rango($filtros);
        $categorias = $filtros['categorias'] ?? [];
        $granularidad = $filtros['granularidad'] ?? $this->granularidadSugerida($desde, $hasta);

        $eventos = $this->cuantificables($categorias)
            ->where('eventos.fecha_hora', '>=', $desde)
            ->where('eventos.fecha_hora', '<=', $hasta)
            ->leftJoin('puntos_monitoreo', 'puntos_monitoreo.id', '=', 'eventos.punto_monitoreo_id')
            ->leftJoin('barrios', 'barrios.id', '=', 'puntos_monitoreo.barrio_id')
            ->get([
                'eventos.fecha_hora',
                'grupos_evento.codigo as categoria',
                'puntos_monitoreo.id as punto_id',
                'puntos_monitoreo.codigo',
                'puntos_monitoreo.nombre',
                'barrios.id as barrio_id',
                'barrios.nombre as barrio',
            ])
            ->map(fn ($e) => [
                'fecha' => Carbon::parse($e->fecha_hora),
                'categoria' => $e->categoria,
                'punto_id' => $e->punto_id,
                'codigo' => $e->codigo,
                'nombre' => $e->nombre,
                'barrio_id' => $e->barrio_id,
                'barrio' => $e->barrio,
            ]);

        return [
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'granularidad' => $granularidad,
            'kpis' => $this->kpis($categorias, $eventos->count(), $eventos),
            'ultimos_meses' => $this->ultimosMeses($categorias),
            'tendencia' => $this->tendencia($eventos, $desde, $hasta, $granularidad),
            'ranking' => [
                'punto' => $this->ranking($eventos, 'punto'),
                'barrio' => $this->ranking($eventos, 'barrio'),
            ],
            'franja_horaria' => $this->franjaHoraria($eventos),
            'actualizado' => now()->format('H:i:s'),
        ];
    }

    /**
     * US-015: totales del mes y del año en curso con su variación respecto del
     * período anterior equivalente (mes anterior completo hasta el mismo día;
     * año anterior hasta la misma fecha), más la distribución por categoría
     * del período elegido.
     */
    private function kpis(array $categorias, int $totalPeriodo, $eventos): array
    {
        $ahora = now();
        $contar = fn (Carbon $d, Carbon $h) => $this->cuantificables($categorias)
            ->whereBetween('eventos.fecha_hora', [$d, $h])->count();

        $mes = $contar($ahora->copy()->startOfMonth(), $ahora);
        $mesAnterior = $contar(
            $ahora->copy()->subMonthNoOverflow()->startOfMonth(),
            $ahora->copy()->subMonthNoOverflow(),
        );
        $anio = $contar($ahora->copy()->startOfYear(), $ahora);
        $anioAnterior = $contar($ahora->copy()->subYear()->startOfYear(), $ahora->copy()->subYear());

        $porCategoria = $eventos->countBy('categoria');

        return [
            'mes' => ['total' => $mes, 'anterior' => $mesAnterior, 'variacion' => $this->variacion($mes, $mesAnterior)],
            'anio' => ['total' => $anio, 'anterior' => $anioAnterior, 'variacion' => $this->variacion($anio, $anioAnterior)],
            'periodo' => $totalPeriodo,
            'por_categoria' => $this->categoriasVisibles()
                ->map(fn ($nombre, $clave) => [
                    'clave' => $clave,
                    'nombre' => $nombre,
                    'cantidad' => (int) ($porCategoria[$clave] ?? 0),
                    'porcentaje' => $totalPeriodo > 0 ? round(($porCategoria[$clave] ?? 0) * 100 / $totalPeriodo, 1) : 0.0,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * US-015: eventos por categoría en los últimos 6 meses (incluye el actual).
     */
    private function ultimosMeses(array $categorias): array
    {
        $inicio = now()->startOfMonth()->subMonths(5);

        $eventos = $this->cuantificables($categorias)
            ->where('eventos.fecha_hora', '>=', $inicio)
            ->get(['eventos.fecha_hora', 'grupos_evento.codigo as categoria']);

        return collect(range(0, 5))->map(function (int $i) use ($inicio, $eventos) {
            $mes = $inicio->copy()->addMonths($i);
            $delMes = $eventos->filter(fn ($e) => Carbon::parse($e->fecha_hora)->isSameMonth($mes));

            return [
                'clave' => $mes->format('Y-m'),
                'etiqueta' => ucfirst($mes->translatedFormat('M Y')),
                'desde' => $mes->toDateString(),
                'hasta' => $mes->copy()->endOfMonth()->toDateString(),
                'total' => $delMes->count(),
                ...$this->categoriasVisibles()->map(fn ($n, $clave) => $delMes->where('categoria', $clave)->count())->all(),
            ];
        })->all();
    }

    /**
     * US-018: evolución por día, semana o mes, total y por categoría.
     */
    private function tendencia($eventos, Carbon $desde, Carbon $hasta, string $granularidad): array
    {
        $tramos = [];
        $cursor = match ($granularidad) {
            'mes' => $desde->copy()->startOfMonth(),
            'semana' => $desde->copy()->startOfWeek(),
            default => $desde->copy()->startOfDay(),
        };

        while ($cursor->lte($hasta)) {
            $fin = match ($granularidad) {
                'mes' => $cursor->copy()->endOfMonth(),
                'semana' => $cursor->copy()->endOfWeek(),
                default => $cursor->copy()->endOfDay(),
            };

            // El drill-down respeta el período elegido aunque el tramo lo exceda.
            $tramoDesde = $cursor->copy()->max($desde);
            $tramoHasta = $fin->copy()->min($hasta);
            $enTramo = $eventos->filter(fn ($e) => $e['fecha']->between($tramoDesde, $tramoHasta));

            $tramos[] = [
                'clave' => $cursor->format('Y-m-d'),
                'etiqueta' => match ($granularidad) {
                    'mes' => ucfirst($cursor->translatedFormat('M Y')),
                    'semana' => 'Sem '.$cursor->format('d/m'),
                    default => $cursor->format('d/m'),
                },
                'desde' => $tramoDesde->toDateString(),
                'hasta' => $tramoHasta->toDateString(),
                'total' => $enTramo->count(),
                ...$this->categoriasVisibles()->map(fn ($n, $clave) => $enTramo->where('categoria', $clave)->count())->all(),
            ];

            $cursor = $fin->copy()->addSecond()->startOfDay();
        }

        return $tramos;
    }

    /**
     * US-019: top 10 de PMs o de barrios. Excluye los eventos sin ubicación
     * (y, para barrios, los PMs sin barrio cargado).
     */
    private function ranking($eventos, string $vista): array
    {
        $porBarrio = $vista === 'barrio';
        $conUbicacion = $eventos->whereNotNull('punto_id');
        $base = $porBarrio ? $conUbicacion->whereNotNull('barrio_id') : $conUbicacion;
        $total = $base->count();

        return $base
            ->groupBy($porBarrio ? 'barrio_id' : 'punto_id')
            ->map(fn ($grupo) => [
                'clave' => (string) $grupo->first()[$porBarrio ? 'barrio_id' : 'punto_id'],
                'nombre' => $porBarrio
                    ? $grupo->first()['barrio']
                    : "{$grupo->first()['codigo']} — {$grupo->first()['nombre']}",
                'cantidad' => $grupo->count(),
                'porcentaje' => $total > 0 ? round($grupo->count() * 100 / $total, 1) : 0.0,
            ])
            ->sortByDesc('cantidad')
            ->take(self::TOP_RANKING)
            ->values()
            ->all();
    }

    /**
     * US-020: distribución por hora del día (0–23) con las 3 de mayor incidencia.
     */
    private function franjaHoraria($eventos): array
    {
        $total = $eventos->count();
        $porHora = $eventos->countBy(fn ($e) => $e['fecha']->hour);

        $horas = collect(range(0, 23))->map(fn (int $h) => [
            'hora' => $h,
            'etiqueta' => sprintf('%02d', $h),
            'cantidad' => (int) ($porHora[$h] ?? 0),
            'porcentaje' => $total > 0 ? round(($porHora[$h] ?? 0) * 100 / $total, 1) : 0.0,
        ]);

        $top = $horas->where('cantidad', '>', 0)->sortByDesc('cantidad')->take(3)->pluck('hora')->all();

        return $horas->map(fn ($h) => [...$h, 'destacada' => in_array($h['hora'], $top, true)])->all();
    }

    private function granularidadSugerida(Carbon $desde, Carbon $hasta): string
    {
        $dias = $desde->diffInDays($hasta);

        return $dias <= 45 ? 'dia' : ($dias <= 180 ? 'semana' : 'mes');
    }

    private function variacion(int $actual, int $anterior): ?float
    {
        return $anterior > 0 ? round(($actual - $anterior) * 100 / $anterior, 1) : null;
    }

    /**
     * Grupos de evento [codigo => nombre] para KPIs y pequeños múltiplos.
     */
    private function categoriasVisibles()
    {
        return collect(GrupoEvento::opciones());
    }

    /**
     * @param  list<string>  $categorias
     * @return Builder<Evento>
     */
    private function cuantificables(array $categorias): Builder
    {
        return Evento::query()
            ->join('tipos_evento', 'tipos_evento.id', '=', 'eventos.tipo_evento_id')
            ->join('grupos_evento', 'grupos_evento.id', '=', 'tipos_evento.grupo_evento_id')
            ->where('tipos_evento.es_cuantificable', true)
            ->when($categorias, fn (Builder $q) => $q->whereIn('grupos_evento.codigo', $categorias));
    }
}
