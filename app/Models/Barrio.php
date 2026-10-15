<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre'])]
class Barrio extends Model
{
    public function puntosMonitoreo(): HasMany
    {
        return $this->hasMany(PuntoMonitoreo::class);
    }
}
