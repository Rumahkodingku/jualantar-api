<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk used by the Storage module when no explicit disk is requested.
    | Development typically uses the private "local" disk, while staging and
    | production point at the S3-compatible "private" bucket.
    |
    */

    'default_disk' => env('STORAGE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Temporary URL Lifetime
    |--------------------------------------------------------------------------
    |
    | Default lifetime (in seconds) used by consumers when generating signed
    | download/upload URLs. Keep sensitive documents short-lived.
    |
    */

    'temporary_url' => [
        'ttl' => (int) env('STORAGE_TEMPORARY_URL_TTL', 300),
    ],

];
