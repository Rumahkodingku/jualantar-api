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

    /*
    |--------------------------------------------------------------------------
    | Product Draft
    |--------------------------------------------------------------------------
    |
    | The catalog "add product" wizard is long and lossy on a phone, so the
    | in-progress form is persisted server-side and resumed on the next visit.
    | A merchant keeps at most one draft; every save pushes its expiry back.
    | The limits only bound the payload, they never require it to be complete.
    |
    */

    'product_draft' => [
        'ttl_days' => (int) env('MERCHANT_PRODUCT_DRAFT_TTL_DAYS', 7),
        'max_variants' => (int) env('MERCHANT_PRODUCT_DRAFT_MAX_VARIANTS', 50),
        'max_modifier_groups' => (int) env('MERCHANT_PRODUCT_DRAFT_MAX_GROUPS', 25),
        'max_modifiers_per_group' => (int) env('MERCHANT_PRODUCT_DRAFT_MAX_MODIFIERS', 30),
        'max_outlets' => (int) env('MERCHANT_PRODUCT_DRAFT_MAX_OUTLETS', 100),
        'preview_url_ttl' => (int) env('MERCHANT_PRODUCT_DRAFT_PREVIEW_TTL', 3600),
    ],

];
