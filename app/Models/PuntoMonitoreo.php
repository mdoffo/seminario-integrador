<?php

namespace App\Models;

use Database\Factories\PuntoMonitoreoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Punto de Monitoreo (PM): ubicación física de una cámara.
 * Campos: codigo, nombre, jurisdiccion (municipal | provincial), zona, latitud, longitud.
 * latitud/longitud alimentan Leaflet + OpenStreetMap en el frontend.
 */
#[Fillable(['codigo', 'nombre', 'jurisdiccion', 'zona', 'latitud', 'longitud'])]
class PuntoMonitoreo extends Model
{
    /** @use HasFactory<PuntoMonitoreoFactory> */
    use HasFactory;

    public const JURISDICCIONES = [
        'municipal' => 'Municipal',
        'provincial' => 'Provincial',
    ];

    /**
     * US-024: rango válido de coordenadas (Villa María y Villa Nueva, con
     * margen para los accesos por ruta).
     * // TODO: confirmar con el equipo si hay PMs provinciales más alejados.
     */
    public const LIMITES = [
        'latitud' => [-32.48, -32.35],
        'longitud' => [-63.33, -63.16],
    ];

    protected $table = 'puntos_monitoreo';

    protected function casts(): array
    {
        return [
            'latitud' => 'float',
            'longitud' => 'float',
        ];
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }
}
