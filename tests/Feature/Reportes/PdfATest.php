<?php

namespace Tests\Feature\Reportes;

use App\Contracts\ConversorPdfAInterface;
use App\Services\Exportadores\PdfExportador;
use App\Services\PdfA\GhostscriptConversorPdfA;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Tests\TestCase;

/**
 * RNF PDF/A: el reporte de turno se archiva en PDF/A-2b (Ghostscript).
 */
class PdfATest extends TestCase
{
    public function test_convierte_el_pdf_de_dompdf_a_pdfa_2b(): void
    {
        if (! Process::run(['gs', '--version'])->successful()) {
            $this->markTestSkipped('Ghostscript no está instalado.');
        }

        $pdf = Pdf::loadHTML('<h1>Reporte de turno</h1><p>Señal en la cámara Nº 12: ¿sin novedad?</p>')->output();

        $pdfa = (new GhostscriptConversorPdfA)->convertir($pdf, 'Reporte de turno #12');

        $this->assertStringStartsWith('%PDF-', $pdfa);
        // Metadatos XMP de identificación PDF/A (parte 2, conformidad B).
        $this->assertMatchesRegularExpression('/pdfaid:part(=[\'"]|>)2/', $pdfa);
        $this->assertMatchesRegularExpression('/pdfaid:conformance(=[\'"]|>)B/', $pdfa);
    }

    public function test_sin_ghostscript_la_conversion_falla_con_una_excepcion(): void
    {
        $this->expectException(RuntimeException::class);

        (new GhostscriptConversorPdfA('ghostscript-inexistente'))->convertir('%PDF-1.7');
    }

    public function test_si_la_conversion_falla_entrega_el_pdf_comun_y_registra_el_error(): void
    {
        $this->app->instance(ConversorPdfAInterface::class, new class implements ConversorPdfAInterface
        {
            public function convertir(string $pdf, ?string $titulo = null): string
            {
                throw new RuntimeException('falló gs');
            }
        });

        $dir = sys_get_temp_dir().'/vistas_pdfa_'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/minimo.blade.php", '<p>Hola</p>');
        View::addNamespace('pruebas_pdfa', $dir);

        Log::spy();

        try {
            $pdf = app(PdfExportador::class)->exportar(['vista' => 'pruebas_pdfa::minimo', 'titulo' => 'Prueba']);
        } finally {
            File::deleteDirectory($dir);
        }

        $this->assertStringStartsWith('%PDF-', $pdf);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $mensaje) => str_contains($mensaje, 'PDF/A'));
    }
}
