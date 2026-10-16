<?php

namespace App\Contracts;

use RuntimeException;

/**
 * Convierte un PDF común al estándar PDF/A (ISO 19005, archivado a largo
 * plazo). RNF: el reporte de turno (Libro de Guardia digital) se archiva en
 * PDF/A, y dompdf no lo genera de forma nativa.
 */
interface ConversorPdfAInterface
{
    /**
     * @param  string  $pdf  Contenido binario del PDF de origen.
     * @param  string|null  $titulo  Título para los metadatos del documento.
     * @return string Contenido binario del PDF/A.
     *
     * @throws RuntimeException si la conversión falla.
     */
    public function convertir(string $pdf, ?string $titulo = null): string;
}
