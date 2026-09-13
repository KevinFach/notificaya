<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ReservaYa — sistema de origen
    |--------------------------------------------------------------------------
    |
    | Valores por defecto para hablar con ReservaYa. Cada origen puede
    | sobrescribir la URL base y siempre aporta su propio token.
    |
    */

    'reservaya' => [
        'base_url' => env('RESERVAYA_BASE_URL', 'https://ry.51x.mx/api/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | FastSMS — sistema destino
    |--------------------------------------------------------------------------
    |
    | La URL base apunta a la raíz de la instalación de FastSMS; el cliente
    | agrega el prefijo /api/v1 de cada endpoint.
    |
    */

    'fastsms' => [
        'base_url' => env('FASTSMS_BASE_URL', 'https://fastsms.test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cliente HTTP
    |--------------------------------------------------------------------------
    |
    | Mismos valores que usa FastSMS para hablar con sus proveedores: 15 s de
    | espera y dos reintentos con backoff creciente.
    |
    */

    'http' => [
        'timeout' => (int) env('NOTIFICAYA_HTTP_TIMEOUT', 15),
        'connect_timeout' => (int) env('NOTIFICAYA_HTTP_CONNECT_TIMEOUT', 5),
        'retry_delays' => [300, 1000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Valores por defecto de un origen nuevo
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'timezone' => env('NOTIFICAYA_DEFAULT_TIMEZONE', 'America/Mexico_City'),
        'phone_prefix' => env('NOTIFICAYA_DEFAULT_PHONE_PREFIX', '+52'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook entrante
    |--------------------------------------------------------------------------
    |
    | Ventana (en segundos) durante la cual una entrega con el mismo cuerpo se
    | considera repetida cuando ReservaYa no manda X-RY-EVENT-ID.
    |
    */

    'webhook' => [
        'deduplication_window' => (int) env('NOTIFICAYA_WEBHOOK_DEDUPE_WINDOW', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resincronización con ReservaYa
    |--------------------------------------------------------------------------
    |
    | ReservaYa limita a 60 solicitudes por minuto y por token, así que la red
    | de seguridad revisa como máximo este número de citas por corrida.
    |
    */

    'resync' => [
        'max_appointments_per_run' => (int) env('NOTIFICAYA_RESYNC_LIMIT', 40),
    ],

];
