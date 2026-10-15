<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo destinatario de notificaciones (p. ej. Guardia Local, Policía).
 * N:M con TipoEvento; 1:N con Contacto.
 */
#[Fillable(['nombre', 'identificador_whatsapp'])]
class GrupoInteresado extends Model
{
    protected $table = 'grupos_interesados';

    public function tiposEvento(): BelongsToMany
    {
        return $this->belongsToMany(TipoEvento::class, 'grupo_interesado_tipo_evento');
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class);
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    /**
     * Destinatarios para el notificador: el grupo de WhatsApp si está
     * configurado; si no, los números de sus contactos.
     *
     * @return list<string>
     */
    public function destinatarios(): array
    {
        if ($this->identificador_whatsapp) {
            return [$this->identificador_whatsapp];
        }

        return $this->contactos->pluck('numero_whatsapp')->all();
    }
}
