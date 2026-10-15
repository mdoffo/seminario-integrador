<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * US-016 / US-017: datos del mapa de eventos y del mapa de calor.
 *
 * Se agrega por Punto de Monitoreo (los eventos se ubican en la posición de su
 * PM). Quedan afuera los eventos sin ubicación y los tipos no cuantificables
 * (Informativos, RN4), igual que en las estadísticas.
 */
class MapaService
{
    /**
     * Período por defecto cuando no se elige uno.
     */
    public const DIAS_POR_DEFECTO = 30;

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{desde: string, hasta: string}
     */
    public function periodo(array $filtros): array
    {
        return [
            'desde' => $filtros['desde'] ?? now()->subDays(self::DIAS_POR_DEFECTO - 1)->toDateString(),
            'hasta' => $filtros['hasta'] ?? now()->toDateString(),
        ];
    }

    /**
     * Cantidad de eventos por PM en el período, con sus coordenadas.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{puntos: list<array<string, mixed>>, total: int, sin_ubicacion: int}
     */
    public function densidad(array $filtros): array
    {
        $periodo = $this->periodo($filtros);
        $base = fn () => $this->cuantificables($filtros, $periodo);

        $puntos = $base()
            ->join('puntos_monitoreo', 'puntos_monitoreo.id', '=', 'eventos.punto_monitoreo_id')
            ->groupBy(
                'puntos_monitoreo.id', 'puntos_monitoreo.codigo', 'puntos_monitoreo.nombre',
                'puntos_monitoreo.jurisdiccion', 'puntos_monitoreo.latitud', 'puntos_monitoreo.longitud',
            )
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'puntos_monitoreo.id',
                'puntos_monitoreo.codigo',
                'puntos_monitoreo.nombre',
                'puntos_monitoreo.jurisdiccion',
                'puntos_monitoreo.latitud',
                'puntos_monitoreo.longitud',
                DB::raw('count(*) as cantidad'),
            ])
            ->map(fn ($p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'jurisdiccion' => $p->jurisdiccion,
                'jurisdiccion_nombre' => PuntoMonitoreo::JURISDICCIONES[$p->jurisdiccion] ?? $p->jurisdiccion,
                'latitud' => (float) $p->latitud,
                'longitud' => (float) $p->longitud,
                'cantidad' => (int) $p->cantidad,
            ])
            ->all();

        return [
            'puntos' => $puntos,
            'total' => array_sum(array_column($puntos, 'cantidad')),
            'sin_ubicacion' => $base()->whereNull('eventos.punto_monitoreo_id')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array{desde: string, hasta: string}  $periodo
     * @return Builder<Evento>
     */
    private function cuantificables(array $filtros, array $periodo): Builder
    {
        return Evento::query()
            ->join('tipos_evento', 'tipos_evento.id', '=', 'eventos.tipo_evento_id')
            ->where('tipos_evento.es_cuantificable', true)
            ->where('eventos.fecha_hora', '>=', Carbon::parse($periodo['desde'])->startOfDay())
            ->where('eventos.fecha_hora', '<', Carbon::parse($periodo['hasta'])->addDay()->startOfDay())
            ->when($filtros['categorias'] ?? null, fn (Builder $q, array $c) => $q->whereIn('tipos_evento.categoria', $c))
            ->when($filtros['tipo_evento_id'] ?? null, fn (Builder $q, $id) => $q->where('eventos.tipo_evento_id', $id));
    }
}
