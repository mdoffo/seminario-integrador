<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Destinatario concreto de WhatsApp. Campos: nombre, numero_whatsapp
 * (formato internacional sin "+", p. ej. 5493534000000), grupo_interesado_id.
 */
#[Fillable(['grupo_interesado_id', 'nombre', 'numero_whatsapp'])]
class Contacto extends Model
{
    protected $table = 'contactos';

    public function grupoInteresado(): BelongsTo
    {
        return $this->belongsTo(GrupoInteresado::class);
    }
}
