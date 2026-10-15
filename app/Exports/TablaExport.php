<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja de cálculo genérica: encabezados + filas ya formateadas.
 * La usa XlsxExportador; no conoce el dominio.
 */
class TablaExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $columnas
     * @param  list<list<scalar|null>>  $filas
     */
    public function __construct(
        private readonly array $columnas,
        private readonly array $filas,
        private readonly string $titulo = 'Datos',
    ) {}

    public function array(): array
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return $this->columnas;
    }

    public function title(): string
    {
        return mb_substr($this->titulo, 0, 31); // límite de Excel para el nombre de la hoja
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
