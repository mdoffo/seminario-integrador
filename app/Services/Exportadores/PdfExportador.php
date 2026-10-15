<?php

namespace App\Services\Exportadores;

use App\Contracts\ExportadorInterface;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Estrategia de exportación a PDF con barryvdh/laravel-dompdf, renderizando
 * una vista Blade PURA de resources/views/reportes/ (sin Inertia).
 *
 * $datos: ['vista' => 'reportes.turno', ...variables de la vista]
 *
 * // TODO: PDF/A (ver CLAUDE.md). dompdf no lo genera nativo; evaluar
 * //       post-proceso con Ghostscript y validación con veraPDF.
 */
class PdfExportador implements ExportadorInterface
{
    public function exportar(iterable $datos): string
    {
        $pdf = Pdf::loadView($datos['vista'], $datos)
            ->setPaper('a4')
            // Solo incrusta los glifos usados: sin esto, cada PDF pesa más de 1 MB.
            ->setOption('isFontSubsettingEnabled', true);

        // Numeración en cada página (dompdf la resuelve al renderizar).
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(
            $canvas->get_width() - 110,
            $canvas->get_height() - 28,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $fuente,
            8,
            [0.35, 0.35, 0.35],
        );

        return $dompdf->output();
    }
}
