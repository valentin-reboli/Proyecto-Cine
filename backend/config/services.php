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

    'n8n' => [
        // Webhook de n8n que recibe las campañas y manda los mensajes (ver CampaniaSender).
        'campanias_webhook' => env('N8N_CAMPANIAS_WEBHOOK'),
        // Webhook de n8n que manda el comprobante y el QR al comprador (ver CompraNotifier).
        'compra_webhook' => env('N8N_COMPRA_WEBHOOK'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mercadopago' => [
        // Credenciales de la aplicacion de Mercado Pago (Tus integraciones > Credenciales de prueba).
        // Con las de prueba no se cobra dinero real. El access token es secreto: solo en el .env / Railway.
        'access_token' => env('MP_ACCESS_TOKEN'),
        'public_key' => env('MP_PUBLIC_KEY'),
        // Clave secreta de las notificaciones (webhooks) para validar la firma x-signature.
        'webhook_secret' => env('MP_WEBHOOK_SECRET'),
        // Muestra en el front el aviso de modo prueba (tarjetas de prueba, sin dinero real).
        'modo_prueba' => (bool) env('MP_MODO_PRUEBA', true),
        // A donde vuelve el comprador despues de pagar en Checkout Pro (la pantalla de reserva del front).
        'url_retorno' => env('MP_URL_RETORNO') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/reserva',
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

];
