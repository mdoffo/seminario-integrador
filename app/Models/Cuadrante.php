<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuadrante (división operativa de la ciudad).
 *
 * // TODO: confirmar con el equipo el listado oficial de cuadrantes; hoy no hay datos.
 */
#[Fillable(['nombre'])]
class Cuadrante extends Model
{
    public function puntosMonitoreo(): HasMany
    {
        return $this->hasMany(PuntoMonitoreo::class);
    }
}
