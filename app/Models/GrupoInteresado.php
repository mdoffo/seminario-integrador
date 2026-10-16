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
#[Fillable(['nombre', 'identificador_whatsapp', 'telegram_chat_id'])]
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
     * Destinatarios para el canal dado (config notificaciones.canal):
     * - telegram: el chat_id del grupo de Telegram.
     * - whatsapp: el grupo de WhatsApp si está configurado; si no, los
     *   números de sus contactos.
     * - log (demo): todo lo que tenga cargado.
     *
     * @return list<string>
     */
    public function destinatarios(string $canal): array
    {
        $telegram = $this->telegram_chat_id ? [$this->telegram_chat_id] : [];
        $whatsapp = $this->identificador_whatsapp
            ? [$this->identificador_whatsapp]
            : $this->contactos->pluck('numero_whatsapp')->all();

        return match ($canal) {
            'telegram' => $telegram,
            'whatsapp' => $whatsapp,
            default => [...$whatsapp, ...$telegram],
        };
    }
}
