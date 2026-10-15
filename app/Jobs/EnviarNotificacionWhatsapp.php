<?php

namespace App\Jobs;

use App\Contracts\NotificadorInterface;
use App\Models\Notificacion;
use App\Models\Parametro;
use App\Services\NotificacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Job en cola (QUEUE_CONNECTION=database) que ejecuta el envío de una
 * Notificacion. US-007: si falla, la cola lo reintenta
 * (cantidad e intervalo: parámetros del sistema de US-029, fijados al
 * despachar el Job); agotados los reintentos, failed() la marca fallida y la audita.
 *
 * DEPENDENCY INVERSION: recibe NotificadorInterface por inyección en handle();
 * el canal concreto (log / WhatsApp) lo resuelve NotificadorFactory.
 */
class EnviarNotificacionWhatsapp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Notificacion $notificacion,
    ) {}

    /**
     * Intento original + reintentos.
     */
    public function tries(): int
    {
        return Parametro::valor(Parametro::NOTIF_REINTENTOS) + 1;
    }

    public function backoff(): int
    {
        return Parametro::valor(Parametro::NOTIF_INTERVALO);
    }

    public function handle(NotificadorInterface $notificador, NotificacionService $notificaciones): void
    {
        $this->notificacion->increment('intentos');

        $grupo = $this->notificacion->grupoInteresado()->with('contactos')->firstOrFail();
        $destinatarios = $grupo->destinatarios();

        // Sin destinatarios reintentar no sirve: se da por fallida de inmediato.
        if ($destinatarios === []) {
            $this->fail(new RuntimeException("El grupo {$grupo->nombre} no tiene destinatarios configurados."));

            return;
        }

        try {
            $notificador->notificar($destinatarios, $this->notificacion->mensaje);
        } catch (Throwable $e) {
            $this->notificacion->update(['error' => $e->getMessage()]);

            throw $e;
        }

        $notificaciones->marcarEnviada($this->notificacion);
    }

    public function failed(Throwable $error): void
    {
        app(NotificacionService::class)->marcarFallida($this->notificacion->fresh(), $error);
    }
}
