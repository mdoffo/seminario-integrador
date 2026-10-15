<?php

/*
 * US-006 / US-007: notificación automática al registrar un evento.
 *
 * canal: 'log' (desarrollo/demo, no envía nada: escribe en el log) o
 *        'whatsapp' (WhatsApp Cloud API de Meta).
 */
return [

    'canal' => env('NOTIFICADOR_CANAL', 'log'),

    // US-007: reintentos automáticos antes de dar el envío por fallido.
    'reintentos' => (int) env('NOTIFICADOR_REINTENTOS', 3),
    'intervalo_reintento' => (int) env('NOTIFICADOR_INTERVALO_REINTENTO', 30), // segundos

    // Solo para el canal 'log': fuerza el fallo para probar reintentos/alertas.
    'simular_fallo' => (bool) env('NOTIFICADOR_SIMULAR_FALLO', false),

    'whatsapp' => [
        'url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('WHATSAPP_TOKEN'),
        'timeout' => 10,
    ],

];
