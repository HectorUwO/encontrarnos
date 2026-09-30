<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    |--------------------------------------------------------------------------
    | Búsqueda de fichas
    |--------------------------------------------------------------------------
    |
    | «meilisearch» usa el servidor de abajo (rápido y tolera errores de
    | escritura en los nombres); «database» busca en MySQL. Si Meilisearch no
    | responde, la búsqueda cae sola a la base de datos.
    |
    */

    /*
    | Quién ve los datos personales de una ficha (nacimiento y domicilio):
    |   admins        solo administradores (valor seguro por omisión)
    |   authenticated cualquier cuenta con sesión iniciada
    |   all           cualquier persona (solo para desarrollo)
    */
    'records_sensitive' => env('RECORDS_SENSITIVE', 'admins'),

    /*
    | Qué fichas se publican al importar:
    |   registry  solo las que el registro nacional autoriza (PublicarFicha = SI)
    |   all       todas; el dato del registro se conserva en `registry_publish`
    */
    'records_publish' => env('RECORDS_PUBLISH', 'registry'),

    'records_search' => env('RECORDS_SEARCH', 'database'),

    // Con «database» en MySQL, busca con el índice de texto completo (más rápido
    // que LIKE). Apágalo si prefieres que las palabras se busquen en cualquier
    // parte del texto.
    'records_fulltext' => (bool) env('RECORDS_FULLTEXT', true),

    'compreface' => [
        'enabled' => (bool) env('COMPREFACE_ENABLED', false),
        'auto_index' => (bool) env('COMPREFACE_AUTO_INDEX', false),
        'host' => env('COMPREFACE_HOST', 'http://127.0.0.1:8001'),
        'key' => env('COMPREFACE_API_KEY'),
        'threshold' => (float) env('COMPREFACE_THRESHOLD', 0.30),
        'timeout' => (int) env('COMPREFACE_TIMEOUT', 20),
        'prediction_count' => 100,
        'limit' => 100,
        'primary_threshold' => 0.80,
        'primary_limit' => 10,
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index' => env('MEILISEARCH_INDEX', 'person_records'),
        'timeout' => (int) env('MEILISEARCH_TIMEOUT', 2),
    ],

];
