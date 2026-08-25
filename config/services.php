<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'apirestevent' => [
        'base_url'    => env('EXTERNAL_API_BASE', 'http://127.0.0.1:8000/api/v1'),
        'retries'     => env('EXTERNAL_API_RETRIES', 1),
        'retry_delay' => env('EXTERNAL_API_RETRY_DELAY_MS', 400),
    ],

    // Frontend público (elascenso/event) — usado solo para armar el link
    // "Ver como participante" / "Vista previa (borrador)" en la edición de
    // evento (ver eventos/edit.blade.php). No es la API, es el sitio SPA.
    'event_frontend' => [
        'base_url' => env('EVENT_FRONTEND_URL', 'http://localhost/elascenso/event/'),
    ],

];
