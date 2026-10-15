<?php

namespace App\Services\Notificadores;

use App\Contracts\NotificadorInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Canal de desarrollo/demo: no envía nada, escribe el mensaje en el log.
 * Permite probar el circuito completo (cola, reintentos, alertas, reenvío)
 * sin cuenta de WhatsApp. Con notificaciones.simular_fallo falla siempre.
 */
class LogNotificador implements NotificadorInterface
{
    public function notificar(array $destinatarios, string $mensaje): void
    {
        if (config('notificaciones.simular_fallo')) {
            throw new RuntimeException('Fallo simulado del canal de notificación.');
        }

        Log::info('[Notificación] '.implode(', ', $destinatarios)."\n".$mensaje);
    }
}
