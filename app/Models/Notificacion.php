<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Envío de la notificación de un Evento a un GrupoInteresado (US-006/US-007).
 * Guarda el resultado para alertar al Supervisor y permitir el reenvío manual.
 */
#[Fillable(['evento_id', 'grupo_interesado_id', 'canal', 'mensaje', 'estado', 'intentos', 'error', 'enviada_at'])]
class Notificacion extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIADA = 'enviada';

    public const ESTADO_FALLIDA = 'fallida';

    protected $table = 'notificaciones';

    protected function casts(): array
    {
        return [
            'enviada_at' => 'datetime',
            'intentos' => 'integer',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function grupoInteresado(): BelongsTo
    {
        return $this->belongsTo(GrupoInteresado::class);
    }
}
