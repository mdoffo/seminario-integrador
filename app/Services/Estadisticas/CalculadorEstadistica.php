<?php

namespace App\Services\Estadisticas;

use App\Contracts\EstadisticaCalculadorInterface;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * US-014: agregados comunes a cualquier período (TEMPLATE METHOD sobre el
 * STRATEGY EstadisticaCalculadorInterface). Cada período concreto define solo
 * cómo se agrupa la evolución temporal (semanas para un mes, meses para un año).
 *
 * Reglas de negocio:
 *  - RN4: los tipos no cuantificables (Informativos) no cuentan; se informa
 *    aparte cuántos hubo.
 *  - Los eventos sin ubicación cuentan en el total y por tipo, pero no en la
 *    distribución por ubicación.
 *  - RN11: se distingue PM municipal de provincial.
 *
 * // TODO: confirmar con el equipo el criterio de Informativos (decisión A/B
 * //       pendiente en docs/backlog.md); el doc los incluye en total y por tipo.
 */
abstract class CalculadorEstadistica implements EstadisticaCalculadorInterface
{
    /**
     * Cantidad de PMs en el ranking.
     */
    public const TOP_PUNTOS = 10;

    /**
     * Tramos de la evolución temporal: [clave, etiqueta, desde, hasta (excluido)].
     *
     * @return list<array{0: string, 1: string, 2: Carbon, 3: Carbon}>
     */
    abstract protected function tramos(Carbon $desde, Carbon $hasta): array;

    public function calcular(DateTimeInterface $desde, DateTimeInterface $hasta): array
    {
        $desde = Carbon::instance($desde);
        $hasta = Carbon::instance($hasta);

        $total = $this->cuantificables($desde, $hasta)->count();
        $sinUbicacion = $this->cuantificables($desde, $hasta)->whereNull('punto_monitoreo_id')->count();

        return [
            'total' => $total,
            'sin_ubicacion' => $sinUbicacion,
            'con_ubicacion' => $total - $sinUbicacion,
            'no_cuantificables' => $this->enPeriodo($desde, $hasta)
                ->whereHas('tipoEvento', fn (Builder $q) => $q->where('es_cuantificable', false))
                ->count(),
            'por_categoria' => $this->porCategoria($desde, $hasta),
            'por_tipo' => $this->porTipo($desde, $hasta),
            'por_jurisdiccion' => $this->porJurisdiccion($desde, $hasta),
            'por_punto' => $this->porPunto($desde, $hasta),
            'evolucion' => $this->evolucion($desde, $hasta),
        ];
    }

    /**
     * @return Builder<Evento>
     */
    private function enPeriodo(Carbon $desde, Carbon $hasta): Builder
    {
        return Evento::query()
            ->where('eventos.fecha_hora', '>=', $desde)
            ->where('eventos.fecha_hora', '<', $hasta);
    }

    /**
     * @return Builder<Evento>
     */
    private function cuantificables(Carbon $desde, Carbon $hasta): Builder
    {
        return $this->enPeriodo($desde, $hasta)
            ->join('tipos_evento', 'tipos_evento.id', '=', 'eventos.tipo_evento_id')
            ->where('tipos_evento.es_cuantificable', true);
    }

    /**
     * Las categorías cuantificables siempre aparecen, aunque tengan 0.
     *
     * @return list<array{clave: string, nombre: string, cantidad: int}>
     */
    private function porCategoria(Carbon $desde, Carbon $hasta): array
    {
        $conteo = $this->cuantificables($desde, $hasta)
            ->groupBy('tipos_evento.categoria')
            ->selectRaw('tipos_evento.categoria as clave, count(*) as cantidad')
            ->pluck('cantidad', 'clave');

        return collect(TipoEvento::CATEGORIAS)
            ->reject(fn ($nombre, $clave) => $clave === 'informativo' && ! $conteo->has($clave))
            ->map(fn ($nombre, $clave) => [
                'clave' => $clave,
                'nombre' => $nombre,
                'cantidad' => (int) ($conteo[$clave] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{nombre: string, categoria: string, cantidad: int}>
     */
    private function porTipo(Carbon $desde, Carbon $hasta): array
    {
        return $this->cuantificables($desde, $hasta)
            ->groupBy('tipos_evento.id', 'tipos_evento.nombre', 'tipos_evento.categoria')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('tipos_evento.nombre')
            ->get(['tipos_evento.nombre', 'tipos_evento.categoria', DB::raw('count(*) as cantidad')])
            ->map(fn ($fila) => [
                'nombre' => $fila->nombre,
                'categoria' => TipoEvento::CATEGORIAS[$fila->categoria] ?? $fila->categoria,
                'cantidad' => (int) $fila->cantidad,
            ])
            ->all();
    }

    /**
     * RN11: municipal vs provincial (solo eventos con ubicación).
     *
     * @return list<array{clave: string, nombre: string, cantidad: int}>
     */
    private function porJurisdiccion(Carbon $desde, Carbon $hasta): array
    {
        $conteo = $this->cuantificables($desde, $hasta)
            ->join('puntos_monitoreo', 'puntos_monitoreo.id', '=', 'eventos.punto_monitoreo_id')
            ->groupBy('puntos_monitoreo.jurisdiccion')
            ->selectRaw('puntos_monitoreo.jurisdiccion as clave, count(*) as cantidad')
            ->pluck('cantidad', 'clave');

        return collect(PuntoMonitoreo::JURISDICCIONES)
            ->map(fn ($nombre, $clave) => [
                'clave' => $clave,
                'nombre' => $nombre,
                'cantidad' => (int) ($conteo[$clave] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Ranking de PMs con más eventos.
     *
     * @return array{puntos_con_eventos: int, ranking: list<array<string, mixed>>}
     */
    private function porPunto(Carbon $desde, Carbon $hasta): array
    {
        $consulta = fn () => $this->cuantificables($desde, $hasta)
            ->join('puntos_monitoreo', 'puntos_monitoreo.id', '=', 'eventos.punto_monitoreo_id');

        return [
            'puntos_con_eventos' => $consulta()->distinct()->count('eventos.punto_monitoreo_id'),
            'ranking' => $consulta()
                ->groupBy('puntos_monitoreo.id', 'puntos_monitoreo.codigo', 'puntos_monitoreo.nombre', 'puntos_monitoreo.jurisdiccion')
                ->orderByDesc(DB::raw('count(*)'))
                ->orderBy('puntos_monitoreo.codigo')
                ->limit(self::TOP_PUNTOS)
                ->get([
                    'puntos_monitoreo.id',
                    'puntos_monitoreo.codigo',
                    'puntos_monitoreo.nombre',
                    'puntos_monitoreo.jurisdiccion',
                    DB::raw('count(*) as cantidad'),
                ])
                ->map(fn ($fila) => [
                    'id' => $fila->id,
                    'codigo' => $fila->codigo,
                    'nombre' => $fila->nombre,
                    'jurisdiccion' => PuntoMonitoreo::JURISDICCIONES[$fila->jurisdiccion] ?? $fila->jurisdiccion,
                    'cantidad' => (int) $fila->cantidad,
                ])
                ->all(),
        ];
    }

    /**
     * Cantidad por tramo y por categoría. Se agrupa en PHP para no depender de
     * funciones de fecha de un motor en particular (PostgreSQL en producción,
     * SQLite en los tests).
     *
     * @return list<array<string, mixed>>
     */
    private function evolucion(Carbon $desde, Carbon $hasta): array
    {
        $tramos = $this->tramos($desde, $hasta);
        $categorias = array_keys(TipoEvento::CATEGORIAS);

        $filas = array_map(fn (array $t) => [
            'clave' => $t[0],
            'etiqueta' => $t[1],
            'total' => 0,
            ...array_fill_keys($categorias, 0),
        ], $tramos);

        $this->cuantificables($desde, $hasta)
            ->select(['eventos.fecha_hora', 'tipos_evento.categoria'])
            ->orderBy('eventos.id')
            ->cursor()
            ->each(function ($evento) use ($tramos, &$filas) {
                $fecha = Carbon::parse($evento->fecha_hora);

                foreach ($tramos as $i => [, , $inicio, $fin]) {
                    if ($fecha->gte($inicio) && $fecha->lt($fin)) {
                        $filas[$i]['total']++;
                        $filas[$i][$evento->categoria]++;

                        return;
                    }
                }
            });

        return $filas;
    }
}
