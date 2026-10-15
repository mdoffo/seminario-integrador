<?php

namespace App\Services\Exportadores;

use App\Contracts\ExportadorInterface;
use App\Exports\TablaExport;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

/**
 * Estrategia de exportación a .xlsx (datos abiertos) con maatwebsite/laravel-excel.
 * El formato xlsx es XML con UTF-8 nativo: tildes y "ñ" se conservan.
 *
 * $datos: ['columnas' => list<string>, 'filas' => list<list<mixed>>, 'titulo' => string]
 */
class XlsxExportador implements ExportadorInterface
{
    public function exportar(iterable $datos): string
    {
        return ExcelFacade::raw(
            new TablaExport($datos['columnas'], $datos['filas'], $datos['titulo'] ?? 'Datos'),
            Excel::XLSX,
        );
    }
}
