<?php

namespace App\Models;

use Database\Factories\PuntoMonitoreoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Punto de Monitoreo (PM): ubicación física de una cámara o alarma.
 * Campos: codigo, nombre, tipo_punto_monitoreo_id, barrio_id y cuadrante_id
 * (opcionales), descripcion, latitud, longitud.
 * latitud/longitud alimentan Leaflet + OpenStreetMap en el frontend.
 */
#[Fillable(['codigo', 'nombre', 'tipo_punto_monitoreo_id', 'barrio_id', 'cuadrante_id', 'descripcion', 'latitud', 'longitud'])]
class PuntoMonitoreo extends Model
{
    /** @use HasFactory<PuntoMonitoreoFactory> */
    use HasFactory;

    /**
     * US-024: rango válido de coordenadas (Villa María y Villa Nueva, con
     * margen para los accesos por ruta). Cubre los 498 PMs del mapa VVU; los
     * más al norte (PM-M-191 a 194) llegan a -32.325.
     */
    public const LIMITES = [
        'latitud' => [-32.48, -32.31],
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

    public function tipoPuntoMonitoreo(): BelongsTo
    {
        return $this->belongsTo(TipoPuntoMonitoreo::class);
    }

    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class);
    }

    public function cuadrante(): BelongsTo
    {
        return $this->belongsTo(Cuadrante::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /**
     * PMs aplanados para los mapas y selectores de la UI.
     * `tipo` es el código de TipoPuntoMonitoreo (municipal | provincial | alarma).
     *
     * @return Collection<int, array{id: int, codigo: string, nombre: string, tipo: string, barrio: ?string, latitud: ?float, longitud: ?float}>
     */
    public static function paraMapa(): Collection
    {
        return static::with(['tipoPuntoMonitoreo:id,codigo', 'barrio:id,nombre'])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'tipo_punto_monitoreo_id', 'barrio_id', 'latitud', 'longitud'])
            ->map(fn (self $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'tipo' => $p->tipoPuntoMonitoreo->codigo,
                'barrio' => $p->barrio?->nombre,
                'latitud' => $p->latitud,
                'longitud' => $p->longitud,
            ])
            ->toBase();
    }
}
