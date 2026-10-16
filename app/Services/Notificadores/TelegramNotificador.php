<?php

namespace App\Services\Notificadores;

use App\Contracts\NotificadorInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Canal de notificación por Telegram Bot API (gratuita).
 *
 * El bot se crea con @BotFather (TELEGRAM_BOT_TOKEN) y se agrega a cada grupo
 * destinatario; el destinatario es el chat_id del grupo (ver el comando
 * `php artisan telegram:chats`). Igual que WhatsApp, se invoca desde el Job en
 * cola y cualquier error lanza excepción para que el Job reintente (US-007).
 */
class TelegramNotificador implements NotificadorInterface
{
    public function notificar(array $destinatarios, string $mensaje): void
    {
        $config = config('notificaciones.telegram');

        if (! $config['token']) {
            throw new RuntimeException('Telegram sin configurar (TELEGRAM_BOT_TOKEN).');
        }

        $url = rtrim($config['url'], '/')."/bot{$config['token']}/sendMessage";

        foreach ($destinatarios as $chatId) {
            $respuesta = Http::timeout($config['timeout'])
                ->acceptJson()
                ->post($url, [
                    'chat_id' => $chatId,
                    'text' => $mensaje,
                ]);

            if ($respuesta->failed() || ! $respuesta->json('ok')) {
                $detalle = $respuesta->json('description') ?? $respuesta->body();

                throw new RuntimeException("Telegram rechazó el envío a {$chatId} (HTTP {$respuesta->status()}): {$detalle}");
            }
        }
    }
}
