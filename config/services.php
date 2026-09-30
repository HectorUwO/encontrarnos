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

    'records_search' => env('RECORDS_SEARCH', 'database'),

    // Con «database» en MySQL, busca con el índice de texto completo (más rápido
    // que LIKE). Apágalo si prefieres que las palabras se busquen en cualquier
    // parte del texto.
    'records_fulltext' => (bool) env('RECORDS_FULLTEXT', true),

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index' => env('MEILISEARCH_INDEX', 'person_records'),
        'timeout' => (int) env('MEILISEARCH_TIMEOUT', 2),
    ],

];
