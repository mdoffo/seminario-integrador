<?php

namespace App\Services;

use App\Models\PuntoMonitoreo;
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
 * listado corrige datos sin duplicar PMs.
 */
class PuntoMonitoreoService
{
    /**
     * Encabezados aceptados (normalizados) => columna.
     */
    private const ALIAS = [
        'codigo' => 'codigo', 'id' => 'codigo', 'pm' => 'codigo', 'codigo_pm' => 'codigo',
        'nombre' => 'nombre', 'ubicacion' => 'nombre', 'descripcion' => 'nombre',
        'jurisdiccion' => 'jurisdiccion', 'tipo' => 'jurisdiccion', 'origen' => 'jurisdiccion',
        'zona' => 'zona', 'cuadrante' => 'zona',
        'latitud' => 'latitud', 'lat' => 'latitud',
        'longitud' => 'longitud', 'lng' => 'longitud', 'lon' => 'longitud', 'long' => 'longitud',
    ];

    /**
     * Reglas de un PM, compartidas por el formulario y la importación.
     *
     * @return array<string, mixed>
     */
    public static function reglas(?int $ignorarId = null): array
    {
        [$latMin, $latMax] = PuntoMonitoreo::LIMITES['latitud'];
        [$lngMin, $lngMax] = PuntoMonitoreo::LIMITES['longitud'];

        return [
            'codigo' => ['required', 'string', 'max:30', Rule::unique('puntos_monitoreo', 'codigo')->ignore($ignorarId)],
            'nombre' => ['required', 'string', 'max:255'],
            'jurisdiccion' => ['required', Rule::in(array_keys(PuntoMonitoreo::JURISDICCIONES))],
            'zona' => ['nullable', 'string', 'max:60'],
            // Coordenadas: las dos o ninguna, dentro de Villa María.
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
            'jurisdiccion.in' => 'La jurisdicción debe ser municipal o provincial.',
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
            return $this->fallo(['El archivo no tiene las columnas obligatorias: codigo, nombre y jurisdiccion.']);
        }

        if ($filas === []) {
            return $this->fallo(['El archivo no tiene filas para importar.']);
        }

        $errores = [];
        $vistos = [];

        foreach ($filas as $i => $fila) {
            $numero = $i + 2; // la fila 1 es el encabezado
            $reglas = self::reglas();
            // Los códigos existentes se actualizan: acá no se exige unicidad contra la base.
            $reglas['codigo'] = ['required', 'string', 'max:30'];

            $v = Validator::make($fila, $reglas, self::mensajes());

            foreach ($v->errors()->all() as $mensaje) {
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
     * Upsert por código.
     *
     * @param  list<array<string, mixed>>  $filas
     */
    public function guardar(array $filas): void
    {
        $ahora = Carbon::now();

        DB::transaction(function () use ($filas, $ahora) {
            foreach (array_chunk($filas, 200) as $lote) {
                PuntoMonitoreo::upsert(
                    array_map(fn (array $f) => [
                        'codigo' => $f['codigo'],
                        'nombre' => $f['nombre'],
                        'jurisdiccion' => $f['jurisdiccion'],
                        'zona' => $f['zona'] ?? null,
                        'latitud' => $f['latitud'] ?? null,
                        'longitud' => $f['longitud'] ?? null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ], $lote),
                    ['codigo'],
                    ['nombre', 'jurisdiccion', 'zona', 'latitud', 'longitud', 'updated_at'],
                );
            }
        });
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

        if (array_diff(['codigo', 'nombre', 'jurisdiccion'], $encabezado) !== []) {
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

            $fila['jurisdiccion'] = isset($fila['jurisdiccion'])
                ? Str::of($fila['jurisdiccion'])->ascii()->lower()->toString()
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
