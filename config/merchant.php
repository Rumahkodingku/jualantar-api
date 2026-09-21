<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Merchant Application URL
    |--------------------------------------------------------------------------
    |
    | Base URL of the merchant PWA. Used to build user-facing links that must
    | land on the frontend (for example the email verification deep link)
    | instead of the API host.
    |
    */

    'app_url' => env('MERCHANT_APP_URL'),

    /*
    |--------------------------------------------------------------------------
    | Merchant Application Deep Link
    |--------------------------------------------------------------------------
    |
    | Absolute URL of the merchant application page in the PWA. Used as the
    | call-to-action target in merchant approval communications. When null the
    | email simply omits the action button.
    |
    */

    'application_url' => env('MERCHANT_APPLICATION_URL'),

    /*
    |--------------------------------------------------------------------------
    | Operations
    |--------------------------------------------------------------------------
    |
    | Operating hours are local times. Until per-outlet timezones exist (P1),
    | availability is resolved against this single business timezone.
    |
    */

    'operations' => [
        'timezone' => env('MERCHANT_OPERATIONS_TIMEZONE', 'Asia/Jakarta'),
    ],

];
