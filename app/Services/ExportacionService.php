<?php

namespace App\Services;

use App\Factories\ExportadorFactory;

/**
 * Punto de entrada de exportación. NO conoce el formato concreto.
 *
 * SINGLE RESPONSIBILITY: coordinar la exportación.
 * FACTORY: pide a ExportadorFactory el ExportadorInterface según el formato
 * ('xlsx' | 'pdf'), evitando if/switch en el Controller.
 */
class ExportacionService
{
    public function __construct(
        private ExportadorFactory $factory,
    ) {}

    /**
     * @return string Contenido binario del archivo generado.
     */
    public function exportar(string $formato, iterable $datos): string
    {
        return $this->factory->crear($formato)->exportar($datos);
    }
}
