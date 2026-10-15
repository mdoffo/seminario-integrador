<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de Punto de Monitoreo: Municipal, Provincial, Alarma comunitaria.
 * Reemplaza a la vieja columna `jurisdiccion`.
 */
#[Fillable(['codigo', 'nombre', 'descripcion'])]
class TipoPuntoMonitoreo extends Model
{
    protected $table = 'tipos_punto_monitoreo';

    public function puntosMonitoreo(): HasMany
    {
        return $this->hasMany(PuntoMonitoreo::class);
    }

    /**
     * Opciones para selects y filtros: [codigo => nombre].
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return static::orderBy('id')->pluck('nombre', 'codigo')->all();
    }
}
