<?php

/*
 * US-006 / US-007: notificación automática al registrar un evento.
 *
 * canal: 'log' (desarrollo/demo, no envía nada: escribe en el log),
 *        'telegram' (Telegram Bot API, gratuita) o
 *        'whatsapp' (WhatsApp Cloud API de Meta).
 */
return [

    'canal' => env('NOTIFICADOR_CANAL', 'log'),

    // Reintentos e intervalo de US-007: parámetros del sistema (US-029),
    // ver App\Models\Parametro.

    // Solo para el canal 'log': fuerza el fallo para probar reintentos/alertas.
    'simular_fallo' => (bool) env('NOTIFICADOR_SIMULAR_FALLO', false),

    'whatsapp' => [
        'url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('WHATSAPP_TOKEN'),
        'timeout' => 10,
    ],

    'telegram' => [
        'url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'timeout' => 10,
    ],

];
