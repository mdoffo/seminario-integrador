<?php

namespace App\Services\PdfA;

use App\Contracts\ConversorPdfAInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * PDF/A-2b con Ghostscript (post-proceso del PDF que genera dompdf).
 *
 * Nivel 2b: apariencia visual garantizada a largo plazo (fuentes incrustadas,
 * perfil de color sRGB como OutputIntent). Ghostscript tiene que estar
 * instalado en el servidor (paquete `ghostscript`); ver config/reportes.php.
 *
 * El bloque PostScript con el OutputIntent se arma acá (receta estándar de
 * pdfmark de la documentación de Ghostscript) en vez de copiar PDFA_def.ps,
 * que es AGPL.
 *
 * // TODO: validar una muestra del reporte real con veraPDF antes de
 * //       entregar (en el entorno de desarrollo no hay Java).
 */
class GhostscriptConversorPdfA implements ConversorPdfAInterface
{
    /**
     * Rutas habituales del perfil sRGB que trae Ghostscript.
     */
    private const PERFILES_ICC = [
        '/usr/share/ghostscript/*/iccprofiles/srgb.icc',
        '/usr/local/share/ghostscript/*/iccprofiles/srgb.icc',
        '/opt/homebrew/share/ghostscript/*/iccprofiles/srgb.icc',
    ];

    public function __construct(
        private readonly string $ghostscript = 'gs',
        private readonly ?string $perfilIcc = null,
        private readonly int $timeout = 60,
    ) {}

    public function convertir(string $pdf, ?string $titulo = null): string
    {
        $icc = $this->perfilIcc();
        $dir = sys_get_temp_dir().'/pdfa_'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($dir);

        try {
            File::put("{$dir}/entrada.pdf", $pdf);
            File::put("{$dir}/pdfa_def.ps", $this->definicion($icc, $titulo));

            $proceso = Process::timeout($this->timeout)->run([
                $this->ghostscript,
                '-q', '-dBATCH', '-dNOPAUSE', '-dSAFER',
                '-dPDFA=2',
                '-sDEVICE=pdfwrite',
                '-sColorConversionStrategy=RGB',
                // 1 = si algo no se puede convertir, se omite y la salida sigue siendo PDF/A.
                '-dPDFACompatibilityPolicy=1',
                "--permit-file-read={$icc}",
                "-sOutputFile={$dir}/salida.pdf",
                "{$dir}/pdfa_def.ps",
                "{$dir}/entrada.pdf",
            ]);

            if ($proceso->failed() || ! File::exists("{$dir}/salida.pdf")) {
                throw new RuntimeException('Ghostscript no pudo generar el PDF/A: '.trim($proceso->errorOutput() ?: $proceso->output()));
            }

            return File::get("{$dir}/salida.pdf");
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function perfilIcc(): string
    {
        if ($this->perfilIcc !== null && $this->perfilIcc !== '') {
            if (! is_readable($this->perfilIcc)) {
                throw new RuntimeException("No se puede leer el perfil ICC configurado: {$this->perfilIcc}");
            }

            return $this->perfilIcc;
        }

        foreach (self::PERFILES_ICC as $patron) {
            $encontrados = glob($patron) ?: [];
            rsort($encontrados); // la versión de Ghostscript más nueva primero

            if ($encontrados !== []) {
                return $encontrados[0];
            }
        }

        throw new RuntimeException('No se encontró el perfil ICC sRGB de Ghostscript: instalar ghostscript o configurar PDFA_PERFIL_ICC.');
    }

    /**
     * PostScript que agrega el título y el OutputIntent sRGB que exige PDF/A.
     */
    private function definicion(string $icc, ?string $titulo): string
    {
        $docinfo = $titulo !== null && $titulo !== ''
            ? '[ /Title '.$this->textoUnicode($titulo)." /DOCINFO pdfmark\n"
            : '';

        return $docinfo.<<<PS
            [/_objdef {icc_PDFA} /type /stream /OBJ pdfmark
            [{icc_PDFA} << /N 3 >> /PUT pdfmark
            [{icc_PDFA} ({$this->escapar($icc)}) (r) file /PUT pdfmark
            [/_objdef {OutputIntent_PDFA} /type /dict /OBJ pdfmark
            [{OutputIntent_PDFA} <<
              /Type /OutputIntent
              /S /GTS_PDFA1
              /DestOutputProfile {icc_PDFA}
              /OutputConditionIdentifier (sRGB)
            >> /PUT pdfmark
            [{Catalog} << /OutputIntents [ {OutputIntent_PDFA} ] >> /PUT pdfmark

            PS;
    }

    /**
     * String hexadecimal UTF-16BE con BOM: admite tildes y "ñ" sin escapar.
     */
    private function textoUnicode(string $texto): string
    {
        return '<FEFF'.strtoupper(bin2hex(mb_convert_encoding($texto, 'UTF-16BE', 'UTF-8'))).'>';
    }

    private function escapar(string $texto): string
    {
        return strtr($texto, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }
}
