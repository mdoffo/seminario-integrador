<?php

namespace App\Listeners;

use App\Events\EventoRegistrado;
use App\Services\NotificacionService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Escucha EventoRegistrado y despacha la notificación a los grupos interesados
 * (US-006). La lógica vive en NotificacionService.
 *
 * ShouldQueue: corre en cola -> el registro del evento responde rápido y no
 * se ve afectado si WhatsApp está lento o caído.
 * Registrado en EventServiceProvider (evento -> listener).
 */
class NotificarInteresadosListener implements ShouldQueue
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    public function handle(EventoRegistrado $event): void
    {
        $this->notificaciones->notificarEvento($event->evento);
    }
}
