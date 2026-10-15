<?php

namespace Database\Seeders;

use App\Services\PuntoMonitoreoService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * US-003: carga los 394 Puntos de Monitoreo (235 municipales + 159 provinciales).
 *
 * - Si existe database/data/puntos_monitoreo.csv lo importa con PuntoMonitoreoService
 *   (idempotente, upsert por código). Encabezado: codigo,nombre,jurisdiccion,zona,latitud,longitud
 * - Si no existe y el entorno es local, genera PMs PLACEHOLDER para poder desarrollar.
 *
 * // TODO: conseguir el listado oficial de PMs (código, nombre, coordenadas).
 */
class PuntoMonitoreoSeeder extends Seeder
{
    public const CANTIDAD_MUNICIPALES = 235;

    public const CANTIDAD_PROVINCIALES = 159;

    public function run(): void
    {
        $csv = database_path('data/puntos_monitoreo.csv');

        if (is_file($csv)) {
            $resultado = app(PuntoMonitoreoService::class)->importar($csv, 'csv');

            if (! $resultado['ok']) {
                throw new RuntimeException("No se pudo importar {$csv}:\n".implode("\n", $resultado['errores']));
            }

            $this->command?->info("Puntos de monitoreo importados desde {$csv}: {$resultado['nuevos']} nuevos, {$resultado['actualizados']} actualizados.");

            return;
        }

        if (! app()->environment('local')) {
            $this->command?->warn('No se encontró '.$csv.': no se cargaron puntos de monitoreo.');

            return;
        }

        app(PuntoMonitoreoService::class)->guardar([
            ...$this->placeholders('municipal', 'M', self::CANTIDAD_MUNICIPALES),
            ...$this->placeholders('provincial', 'P', self::CANTIDAD_PROVINCIALES),
        ]);
        $this->command?->warn('Se generaron puntos de monitoreo PLACEHOLDER (solo desarrollo).');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function placeholders(string $jurisdiccion, string $prefijo, int $cantidad): array
    {
        return array_map(fn (int $n) => [
            'codigo' => sprintf('PM-%s-%03d', $prefijo, $n),
            'nombre' => sprintf('PM %s %03d (placeholder)', $jurisdiccion, $n),
            'jurisdiccion' => $jurisdiccion,
            'latitud' => fake()->latitude(-32.44, -32.38),
            'longitud' => fake()->longitude(-63.28, -63.20),
        ], range(1, $cantidad));
    }
}
