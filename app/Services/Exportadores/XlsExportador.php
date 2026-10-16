<?php

namespace App\Services\Exportadores;

use App\Contracts\ExportadorInterface;
use App\Exports\TablaExport;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

/**
 * Estrategia de exportación a .xls (Excel 97-2003, BIFF8), que es el formato
 * que pide el doc en US-013. BIFF8 guarda el texto en Unicode: tildes y "ñ"
 * se conservan. Límite del formato: 65.536 filas por hoja.
 *
 * $datos: ['columnas' => list<string>, 'filas' => list<list<mixed>>, 'titulo' => string]
 */
class XlsExportador implements ExportadorInterface
{
    /** Filas de datos que entran en una hoja .xls (65.536 menos el encabezado). */
    public const MAX_FILAS = 65535;

    public function exportar(iterable $datos): string
    {
        return ExcelFacade::raw(
            new TablaExport($datos['columnas'], $datos['filas'], $datos['titulo'] ?? 'Datos'),
            Excel::XLS,
        );
    }
}
