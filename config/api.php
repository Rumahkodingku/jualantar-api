<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | The version segment prefixed to every API route group.
    |
    */

    'version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | Problem Details (RFC 9457)
    |--------------------------------------------------------------------------
    |
    | base_url is the URI prefix used to build each problem type. Every error
    | code maps to {base_url}/{code}. Unexpected server errors use "about:blank".
    |
    */

    'problem' => [
        'base_url' => env('API_PROBLEM_BASE_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/problems'),
        'generic_type' => 'about:blank',
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Code Catalog
    |--------------------------------------------------------------------------
    |
    | Single source of truth for machine-readable error codes. Each entry maps
    | a code to its HTTP status and default title.
    |
    */

    'codes' => [
        'bad_request' => ['status' => 400, 'title' => 'Bad Request'],
        'unauthenticated' => ['status' => 401, 'title' => 'Unauthenticated'],
        'forbidden' => ['status' => 403, 'title' => 'Forbidden'],
        'not_found' => ['status' => 404, 'title' => 'Not Found'],
        'method_not_allowed' => ['status' => 405, 'title' => 'Method Not Allowed'],
        'conflict' => ['status' => 409, 'title' => 'Conflict'],
        'unsupported_media_type' => ['status' => 415, 'title' => 'Unsupported Media Type'],
        'validation_error' => ['status' => 422, 'title' => 'Validation Failed'],
        'unprocessable_entity' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'rate_limited' => ['status' => 429, 'title' => 'Too Many Requests'],
        'internal_server_error' => ['status' => 500, 'title' => 'Internal Server Error'],
    ],

];
