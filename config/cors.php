<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Origins are environment-driven so local, LAN/mobile, and production can be
| configured without code changes. Values are comma-separated. Entries may
| contain "*" wildcards (converted to patterns by fruitcake/php-cors), e.g.
| "http://192.168.*:*" for LAN development.
|
| Authentication uses Sanctum personal access tokens (Authorization: Bearer),
| so credentials/cookies are not required.
|
*/

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')),
)));

$allowedOriginsPatterns = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', '')),
)));

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => $allowedOriginsPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => ['*'],

    'max_age' => 0,

    'supports_credentials' => false,

];
