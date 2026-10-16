<?php

namespace App\Services\Exportadores;

use App\Contracts\ConversorPdfAInterface;
use App\Contracts\ExportadorInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Estrategia de exportación a PDF con barryvdh/laravel-dompdf, renderizando
 * una vista Blade PURA de resources/views/reportes/ (sin Inertia).
 *
 * $datos: ['vista' => 'reportes.turno', 'titulo' => '...', ...variables de la vista]
 *
 * RNF PDF/A: dompdf no lo genera nativo, así que el resultado pasa por el
 * ConversorPdfAInterface (Ghostscript, ver config/reportes.php). Si la
 * conversión falla se entrega el PDF común y se registra el error: el reporte
 * de turno no puede quedar inaccesible.
 */
class PdfExportador implements ExportadorInterface
{
    public function __construct(private readonly ConversorPdfAInterface $pdfa) {}

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

        $pdf = $dompdf->output();

        try {
            return $this->pdfa->convertir($pdf, $datos['titulo'] ?? null);
        } catch (RuntimeException $e) {
            Log::error('No se pudo convertir el reporte a PDF/A; se entrega como PDF común.', [
                'vista' => $datos['vista'],
                'error' => $e->getMessage(),
            ]);

            return $pdf;
        }
    }
}
