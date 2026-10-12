<?php

namespace App\Models;

use Database\Factories\TipoEventoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campos: grupo_evento_id (ver GrupoEvento), nombre (único por grupo), descripcion,
 * requiere_ubicacion (bool)  -> si false, el Evento puede no tener PuntoMonitoreo.
 * es_cuantificable (bool)     -> si false, NO cuenta en estadísticas.
 * notifica (bool)             -> si true, al registrarse se avisa por WhatsApp.
 * Los flags son independientes entre sí.
 */
#[Fillable(['grupo_evento_id', 'nombre', 'descripcion', 'requiere_ubicacion', 'es_cuantificable', 'notifica'])]
class TipoEvento extends Model
{
    /** @use HasFactory<TipoEventoFactory> */
    use HasFactory;

    protected $table = 'tipos_evento';

    protected function casts(): array
    {
        return [
            'requiere_ubicacion' => 'boolean',
            'es_cuantificable' => 'boolean',
            'notifica' => 'boolean',
        ];
    }

    public function grupoEvento(): BelongsTo
    {
        return $this->belongsTo(GrupoEvento::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    // TODO: interesados a notificar cuando exista Interesado (etapa 4).
}
