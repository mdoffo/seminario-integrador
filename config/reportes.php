<?php

/*
 * RNF PDF/A: el reporte de turno (Libro de Guardia digital) se archiva en
 * PDF/A-2b. dompdf genera el PDF y Ghostscript lo convierte.
 *
 * Si la conversión falla (p. ej. falta Ghostscript), el reporte se entrega
 * igual como PDF común y queda un error en el log: el centro de monitoreo no
 * puede quedarse sin el reporte de turno.
 */
return [

    'pdfa' => [
        'habilitado' => (bool) env('PDFA_HABILITADO', true),

        // Ejecutable de Ghostscript (nombre en el PATH o ruta absoluta).
        'ghostscript' => env('GHOSTSCRIPT_BIN', 'gs'),

        // Perfil ICC sRGB del OutputIntent. Vacío = el que trae Ghostscript.
        'perfil_icc' => env('PDFA_PERFIL_ICC'),

        // Segundos máximos de la conversión.
        'timeout' => (int) env('PDFA_TIMEOUT', 60),
    ],

];
