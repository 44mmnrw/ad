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

    'yandex_maps' => [
        'static_api_key' => env('YANDEX_STATIC_MAPS_API_KEY'),
        'js_http_geocoder_api_key' => env('YANDEX_JS_HTTP_GEOCODER_API_KEY'),
        'js_api_key' => env('YANDEX_JS_API_KEY', env('YANDEX_JS_HTTP_GEOCODER_API_KEY')),
        'http_geocoder_api_key' => env('YANDEX_HTTP_GEOCODER_API_KEY', env('YANDEX_GEOCODER_API_KEY', env('YANDEX_JS_HTTP_GEOCODER_API_KEY'))),
        'geocoder_api_key' => env('YANDEX_GEOCODER_API_KEY', env('YANDEX_HTTP_GEOCODER_API_KEY', env('YANDEX_JS_HTTP_GEOCODER_API_KEY'))),
        'geocoder_url' => env('YANDEX_GEOCODER_URL', 'https://geocode-maps.yandex.ru/1.x/'),
        'router_api_key' => env('YANDEX_ROUTER_API_KEY'),
        'router_url' => env('YANDEX_ROUTER_URL', 'https://api.routing.yandex.net/v2/route'),
        'geosuggest_api_key' => env('YANDEX_GEOSUGGEST_API_KEY', env('YANDEX_JS_HTTP_GEOCODER_API_KEY')),
        'geosuggest_url' => env('YANDEX_GEOSUGGEST_URL', 'https://suggest-maps.yandex.ru/v1/suggest'),
        'timeout' => (int) env('YANDEX_GEOCODER_TIMEOUT', 10),
    ],

    'dadata' => [
        'api_key' => env('DADATA_API_KEY'),
        'secret_key' => env('DADATA_SECRET_KEY'),
        'timeout' => (int) env('DADATA_TIMEOUT', 10),
        'find_party_url' => env('DADATA_FIND_PARTY_URL', 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party'),
        'suggest_party_url' => env('DADATA_SUGGEST_PARTY_URL', 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party'),
        'find_bank_url' => env('DADATA_FIND_BANK_URL', 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/bank'),
    ],

];
