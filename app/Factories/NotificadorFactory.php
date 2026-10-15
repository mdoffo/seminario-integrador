<?php

namespace App\Factories;

use App\Contracts\NotificadorInterface;
use App\Services\Notificadores\LogNotificador;
use App\Services\Notificadores\WhatsappNotificador;
use InvalidArgumentException;

/**
 * FACTORY: resuelve el canal de notificación concreto (config notificaciones.canal).
 * Un canal nuevo (email/SMS) se agrega acá sin tocar a quien la usa (OPEN/CLOSED).
 */
class NotificadorFactory
{
    public function crear(?string $canal = null): NotificadorInterface
    {
        $canal ??= config('notificaciones.canal');

        return match ($canal) {
            'whatsapp' => new WhatsappNotificador,
            'log' => new LogNotificador,
            // 'email' => new EmailNotificador(),  // TODO a futuro
            default => throw new InvalidArgumentException("Canal no soportado: {$canal}"),
        };
    }
}
