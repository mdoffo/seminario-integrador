<?php

namespace Database\Seeders;

use App\Models\Barrio;
use App\Models\PuntoMonitoreo;
use App\Models\TipoPuntoMonitoreo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * US-003: carga los Puntos de Monitoreo desde database/data/puntos_monitoreo.csv
 * (idempotente: inserta los códigos que todavía no existen).
 *
 * El CSV se generó del mapa "VVU VM" de Google My Maps del Centro de Monitoreo:
 * capas "MVM VVU200 Funcionando" (municipal), "GobCBA" + "Relevamiento Policía
 * Cba" (provincial) y "ALARMAS VILLA MARIA FINAL" (alarma). Encabezado (UTF-8):
 *   codigo,nombre,tipo,barrio,descripcion,latitud,longitud
 * `tipo` es el código de TipoPuntoMonitoreo; `barrio` puede venir vacío.
 *
 * // TODO: las cámaras municipales 143, 195, 204, 215, 232, 250 y 255–339 no
 * // figuran en el mapa (sin nombre ni coordenadas). Agregarlas al CSV cuando
 * // el equipo consiga el listado.
 */
class PuntoMonitoreoSeeder extends Seeder
{
    public const TIPOS = [
        'municipal' => 'Municipal',
        'provincial' => 'Provincial',
        'alarma' => 'Alarma comunitaria',
    ];

    /** Nombre de los PMs de prueba que generaba la versión anterior del seeder. */
    private const MARCA_PLACEHOLDER = '%(placeholder)';

    public function run(): void
    {
        $tipos = [];
        foreach (self::TIPOS as $codigo => $nombre) {
            $tipos[$codigo] = TipoPuntoMonitoreo::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre])->id;
        }

        $csv = database_path('data/puntos_monitoreo.csv');

        if (! is_file($csv)) {
            $this->command?->warn('No se encontró '.$csv.': no se cargaron puntos de monitoreo.');

            return;
        }

        // El CSV es la carga inicial: deploy.sh corre este seeder en cada deploy,
        // así que no se pisan los PMs ya cargados (el Administrador de sistema
        // puede haberles asignado barrio/cuadrante). Solo se reemplazan los
        // placeholder que generaba la versión anterior de este seeder.
        $existentes = PuntoMonitoreo::where('nombre', 'not like', self::MARCA_PLACEHOLDER)->pluck('codigo')->flip();
        $nuevos = array_values(array_filter(
            $this->leerCsv($csv, $tipos),
            fn (array $f) => ! isset($existentes[$f['codigo']]),
        ));
        $this->upsert($nuevos);

        PuntoMonitoreo::where('nombre', 'like', self::MARCA_PLACEHOLDER)->doesntHave('eventos')->delete();

        $this->command?->info(count($nuevos).' puntos de monitoreo nuevos importados desde '.$csv);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function upsert(array $filas): void
    {
        $ahora = Carbon::now();

        $filas = array_map(fn (array $f) => [...$f, 'created_at' => $ahora, 'updated_at' => $ahora], $filas);

        foreach (array_chunk($filas, 200) as $lote) {
            PuntoMonitoreo::upsert($lote, ['codigo'], [
                'nombre', 'tipo_punto_monitoreo_id', 'barrio_id', 'descripcion', 'latitud', 'longitud', 'updated_at',
            ]);
        }
    }

    /**
     * @param  array<string, int>  $tipos  codigo => id
     * @return list<array<string, mixed>>
     */
    private function leerCsv(string $ruta, array $tipos): array
    {
        $handle = fopen($ruta, 'r');
        $encabezado = array_map(fn ($c) => trim((string) $c, " \t\n\r\0\x0B\u{FEFF}"), fgetcsv($handle, escape: ''));
        $barrios = [];
        $filas = [];

        while (($linea = fgetcsv($handle, escape: '')) !== false) {
            if ($linea === [null]) {
                continue;
            }

            $f = array_combine($encabezado, array_map('trim', $linea));

            if (! isset($tipos[$f['tipo']])) {
                throw new RuntimeException("Tipo de punto inválido en PM {$f['codigo']}: {$f['tipo']}");
            }

            if ($f['barrio'] !== '' && ! isset($barrios[$f['barrio']])) {
                $barrios[$f['barrio']] = Barrio::firstOrCreate(['nombre' => $f['barrio']])->id;
            }

            $filas[] = [
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'tipo_punto_monitoreo_id' => $tipos[$f['tipo']],
                'barrio_id' => $f['barrio'] !== '' ? $barrios[$f['barrio']] : null,
                'descripcion' => $f['descripcion'] !== '' ? $f['descripcion'] : null,
                'latitud' => $f['latitud'] !== '' ? (float) $f['latitud'] : null,
                'longitud' => $f['longitud'] !== '' ? (float) $f['longitud'] : null,
            ];
        }

        fclose($handle);

        return $filas;
    }
}
