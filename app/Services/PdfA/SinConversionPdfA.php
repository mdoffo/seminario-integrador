<?php

namespace App\Services\PdfA;

use App\Contracts\ConversorPdfAInterface;

/**
 * NULL OBJECT: devuelve el PDF tal cual. Se usa cuando la conversión está
 * deshabilitada (PDFA_HABILITADO=false), p. ej. en los tests o en un entorno
 * sin Ghostscript.
 */
class SinConversionPdfA implements ConversorPdfAInterface
{
    public function convertir(string $pdf, ?string $titulo = null): string
    {
        return $pdf;
    }
}
