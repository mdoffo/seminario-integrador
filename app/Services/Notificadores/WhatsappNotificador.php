<?php

namespace App\Services\Notificadores;

use App\Contracts\NotificadorInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Canal de notificación por WhatsApp Cloud API (Meta). NO Twilio.
 *
 * Se invoca desde el Job EnviarNotificacionWhatsapp (en cola), nunca de
 * forma síncrona dentro del registro del evento. Cualquier error HTTP lanza
 * excepción para que el Job reintente (US-007).
 *
 * // TODO: confirmar con el equipo/Meta:
 * //  - si la Cloud API permite enviar a grupos (hoy se envía a cada número);
 * //  - fuera de la ventana de 24 h Meta exige plantillas aprobadas: el texto
 * //    libre solo sirve para pruebas. Ajustar el payload a 'template'.
 */
class WhatsappNotificador implements NotificadorInterface
{
    public function notificar(array $destinatarios, string $mensaje): void
    {
        $config = config('notificaciones.whatsapp');

        if (! $config['phone_number_id'] || ! $config['token']) {
            throw new RuntimeException('WhatsApp Cloud API sin configurar (WHATSAPP_PHONE_NUMBER_ID / WHATSAPP_TOKEN).');
        }

        $url = rtrim($config['url'], '/')."/{$config['phone_number_id']}/messages";

        foreach ($destinatarios as $numero) {
            $respuesta = Http::withToken($config['token'])
                ->timeout($config['timeout'])
                ->acceptJson()
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $numero,
                    'type' => 'text',
                    'text' => ['body' => $mensaje],
                ]);

            if ($respuesta->failed()) {
                $detalle = $respuesta->json('error.message') ?? $respuesta->body();

                throw new RuntimeException("WhatsApp rechazó el envío a {$numero} (HTTP {$respuesta->status()}): {$detalle}");
            }
        }
    }
}
