<?php

namespace App\Services;

use App\Models\Barrio;
use App\Models\Cuadrante;
use App\Models\PuntoMonitoreo;
use App\Models\TipoPuntoMonitoreo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

/**
 * US-024: alta masiva y validación de Puntos de Monitoreo.
 *
 * Importación TODO O NADA: si una fila tiene errores no se guarda ninguna y
 * se informa fila por fila. Actualiza por código (upsert): reimportar el
 * listado corrige datos sin duplicar PMs. Usa el mismo formato que
 * database/data/puntos_monitoreo.csv (tipo = código de TipoPuntoMonitoreo;
 * barrio y cuadrante por nombre, se crean si no existen).
 */
class PuntoMonitoreoService
{
    /**
     * Encabezados aceptados (normalizados) => columna.
     */
    private const ALIAS = [
        'codigo' => 'codigo', 'id' => 'codigo', 'pm' => 'codigo', 'codigo_pm' => 'codigo',
        'nombre' => 'nombre', 'ubicacion' => 'nombre',
        'tipo' => 'tipo', 'tipo_punto' => 'tipo', 'jurisdiccion' => 'tipo', 'origen' => 'tipo',
        'barrio' => 'barrio',
        'cuadrante' => 'cuadrante', 'zona' => 'cuadrante',
        'descripcion' => 'descripcion', 'observaciones' => 'descripcion',
        'latitud' => 'latitud', 'lat' => 'latitud',
        'longitud' => 'longitud', 'lng' => 'longitud', 'lon' => 'longitud', 'long' => 'longitud',
    ];

    /**
     * Reglas del formulario (catálogos por id).
     *
     * @return array<string, mixed>
     */
    public static function reglas(?int $ignorarId = null): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', Rule::unique('puntos_monitoreo', 'codigo')->ignore($ignorarId)],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_punto_monitoreo_id' => ['required', 'integer', 'exists:tipos_punto_monitoreo,id'],
            'barrio_id' => ['nullable', 'integer', 'exists:barrios,id'],
            'cuadrante_id' => ['nullable', 'integer', 'exists:cuadrantes,id'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            ...self::reglasCoordenadas(),
        ];
    }

    /**
     * Coordenadas: las dos o ninguna, dentro del área de Villa María.
     *
     * @return array<string, mixed>
     */
    private static function reglasCoordenadas(): array
    {
        [$latMin, $latMax] = PuntoMonitoreo::LIMITES['latitud'];
        [$lngMin, $lngMax] = PuntoMonitoreo::LIMITES['longitud'];

        return [
            'latitud' => ['nullable', 'required_with:longitud', 'numeric', "between:{$latMin},{$latMax}"],
            'longitud' => ['nullable', 'required_with:latitud', 'numeric', "between:{$lngMin},{$lngMax}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'latitud.between' => 'La latitud está fuera de Villa María.',
            'longitud.between' => 'La longitud está fuera de Villa María.',
            'latitud.required_with' => 'Falta la latitud (las coordenadas van juntas).',
            'longitud.required_with' => 'Falta la longitud (las coordenadas van juntas).',
            'tipo_punto_monitoreo_id.required' => 'Elegí el tipo de punto.',
            'tipo.in' => 'El tipo debe ser '.implode(', ', array_keys(TipoPuntoMonitoreo::opciones())).'.',
        ];
    }

    /**
     * Importa un archivo .csv, .xlsx o .xls.
     *
     * @return array{ok: bool, errores: list<string>, nuevos: int, actualizados: int}
     */
    public function importar(string $ruta, string $extension): array
    {
        $filas = $this->leer($ruta, strtolower($extension));

        if ($filas === null) {
            return $this->fallo(['El archivo no tiene las columnas obligatorias: codigo, nombre y tipo.']);
        }

        if ($filas === []) {
            return $this->fallo(['El archivo no tiene filas para importar.']);
        }

        $reglas = [
            // Los códigos existentes se actualizan: acá no se exige unicidad contra la base.
            'codigo' => ['required', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(array_keys(TipoPuntoMonitoreo::opciones()))],
            'barrio' => ['nullable', 'string', 'max:255'],
            'cuadrante' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            ...self::reglasCoordenadas(),
        ];
        $errores = [];
        $vistos = [];

        foreach ($filas as $i => $fila) {
            $numero = $i + 2; // la fila 1 es el encabezado

            foreach (Validator::make($fila, $reglas, self::mensajes())->errors()->all() as $mensaje) {
                $errores[] = "Fila {$numero}: {$mensaje}";
            }

            $codigo = $fila['codigo'] ?? null;
            if ($codigo !== null && isset($vistos[$codigo])) {
                $errores[] = "Fila {$numero}: el código {$codigo} está repetido (también en la fila {$vistos[$codigo]}).";
            }
            $vistos[$codigo] = $numero;
        }

        if ($errores !== []) {
            return $this->fallo($errores);
        }

        $existentes = PuntoMonitoreo::whereIn('codigo', array_column($filas, 'codigo'))->count();
        $this->guardar($filas);

        return ['ok' => true, 'errores' => [], 'nuevos' => count($filas) - $existentes, 'actualizados' => $existentes];
    }

    /**
     * Upsert por código. Resuelve tipo, barrio y cuadrante a sus ids.
     *
     * @param  list<array<string, mixed>>  $filas
     */
    private function guardar(array $filas): void
    {
        $ahora = Carbon::now();
        $tipos = TipoPuntoMonitoreo::pluck('id', 'codigo');

        DB::transaction(function () use ($filas, $ahora, $tipos) {
            $barrios = $this->catalogo(Barrio::class, array_column($filas, 'barrio'));
            $cuadrantes = $this->catalogo(Cuadrante::class, array_column($filas, 'cuadrante'));

            foreach (array_chunk($filas, 200) as $lote) {
                PuntoMonitoreo::upsert(
                    array_map(fn (array $f) => [
                        'codigo' => $f['codigo'],
                        'nombre' => $f['nombre'],
                        'tipo_punto_monitoreo_id' => $tipos[$f['tipo']],
                        'barrio_id' => isset($f['barrio']) ? $barrios[$f['barrio']] : null,
                        'cuadrante_id' => isset($f['cuadrante']) ? $cuadrantes[$f['cuadrante']] : null,
                        'descripcion' => $f['descripcion'] ?? null,
                        'latitud' => $f['latitud'] ?? null,
                        'longitud' => $f['longitud'] ?? null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ], $lote),
                    ['codigo'],
                    ['nombre', 'tipo_punto_monitoreo_id', 'barrio_id', 'cuadrante_id', 'descripcion', 'latitud', 'longitud', 'updated_at'],
                );
            }
        });
    }

    /**
     * nombre => id de Barrio/Cuadrante; crea los que no existen.
     *
     * @param  class-string<Barrio|Cuadrante>  $modelo
     * @param  list<?string>  $nombres
     * @return array<string, int>
     */
    private function catalogo(string $modelo, array $nombres): array
    {
        return collect($nombres)->filter()->unique()
            ->mapWithKeys(fn (string $nombre) => [$nombre => $modelo::firstOrCreate(['nombre' => $nombre])->id])
            ->all();
    }

    /**
     * Filas normalizadas, o null si faltan columnas obligatorias.
     *
     * @return list<array<string, mixed>>|null
     */
    private function leer(string $ruta, string $extension): ?array
    {
        $lector = IOFactory::createReader(match ($extension) {
            'csv', 'txt' => 'Csv',
            'xls' => 'Xls',
            default => 'Xlsx',
        });

        if ($lector instanceof Csv) {
            $lector->setInputEncoding('UTF-8'); // el separador (, o ;) se detecta solo
        }

        $hoja = $lector->load($ruta)->getActiveSheet()->toArray(null, true, false);
        $encabezado = array_map(
            fn ($c) => self::ALIAS[Str::of((string) $c)->replace("\u{FEFF}", '')->ascii()->lower()->trim()->snake()->toString()] ?? null,
            array_shift($hoja) ?? [],
        );

        if (array_diff(['codigo', 'nombre', 'tipo'], $encabezado) !== []) {
            return null;
        }

        $filas = [];

        foreach ($hoja as $celdas) {
            $fila = [];

            foreach ($encabezado as $j => $columna) {
                if ($columna !== null) {
                    $valor = trim((string) ($celdas[$j] ?? ''));
                    $fila[$columna] = $valor === '' ? null : $valor;
                }
            }

            if (array_filter($fila, fn ($v) => $v !== null) === []) {
                continue; // fila vacía
            }

            $fila['tipo'] = isset($fila['tipo'])
                ? Str::of($fila['tipo'])->ascii()->lower()->toString()
                : null;

            // Coma decimal (planillas en español): -32,4075 -> -32.4075.
            foreach (['latitud', 'longitud'] as $coord) {
                if (isset($fila[$coord])) {
                    $fila[$coord] = str_replace(',', '.', $fila[$coord]);
                }
            }

            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @param  list<string>  $errores
     * @return array{ok: false, errores: list<string>, nuevos: int, actualizados: int}
     */
    private function fallo(array $errores): array
    {
        return ['ok' => false, 'errores' => $errores, 'nuevos' => 0, 'actualizados' => 0];
    }
}
