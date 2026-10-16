<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Lista los chats donde el bot de Telegram recibió mensajes recientes, para
 * obtener el chat_id que se carga en cada grupo interesado (US-028).
 *
 * Uso: agregar el bot al grupo, escribir cualquier mensaje en el grupo
 * (p. ej. "/start@NombreDelBot") y correr `php artisan telegram:chats`.
 * Telegram guarda esos mensajes 24 h.
 */
class TelegramChats extends Command
{
    protected $signature = 'telegram:chats';

    protected $description = 'Muestra el chat_id de los grupos donde está el bot de Telegram';

    public function handle(): int
    {
        $config = config('notificaciones.telegram');

        if (! $config['token']) {
            $this->error('Falta TELEGRAM_BOT_TOKEN en el .env.');

            return self::FAILURE;
        }

        $respuesta = Http::timeout($config['timeout'])
            ->get(rtrim($config['url'], '/')."/bot{$config['token']}/getUpdates");

        if ($respuesta->failed() || ! $respuesta->json('ok')) {
            $this->error('Telegram respondió: '.($respuesta->json('description') ?? $respuesta->body()));

            return self::FAILURE;
        }

        $chats = collect($respuesta->json('result'))
            ->map(fn ($u) => data_get($u, 'message.chat') ?? data_get($u, 'my_chat_member.chat') ?? data_get($u, 'channel_post.chat'))
            ->filter()
            ->unique('id')
            ->map(fn ($c) => [$c['id'], $c['type'], $c['title'] ?? trim(($c['first_name'] ?? '').' '.($c['last_name'] ?? ''))])
            ->values();

        if ($chats->isEmpty()) {
            $this->warn('No hay mensajes recientes. Agregá el bot al grupo, escribí un mensaje en el grupo y volvé a correr el comando.');

            return self::SUCCESS;
        }

        $this->table(['chat_id', 'Tipo', 'Nombre'], $chats->all());

        return self::SUCCESS;
    }
}
