<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery Queue
    |--------------------------------------------------------------------------
    |
    | Out-of-app delivery is asynchronous by default. The job is pushed to this
    | queue; tests run with the sync queue and execute it inline.
    |
    */

    'queue' => env('COMMUNICATIONS_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Retry Policy
    |--------------------------------------------------------------------------
    |
    | Transient provider failures are retried this many times before the
    | communication is marked failed. backoff is the number of seconds to wait
    | between attempts.
    |
    */

    'tries' => (int) env('COMMUNICATIONS_TRIES', 3),

    'backoff' => [60, 300],

    /*
    |--------------------------------------------------------------------------
    | Sender Identity
    |--------------------------------------------------------------------------
    |
    | Reuses the existing Laravel mail configuration so no duplicate mail
    | variables are introduced.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'JualAntar')),
    ],

    'default_subject' => env('COMMUNICATIONS_DEFAULT_SUBJECT', env('APP_NAME', 'JualAntar')),

    /*
    |--------------------------------------------------------------------------
    | Template View Namespaces
    |--------------------------------------------------------------------------
    |
    | The renderer resolves a communication's `template` identifier inside these
    | view namespaces, in order. Communications ships a generic template only;
    | producers register their own namespace/templates for business content.
    |
    */

    'templates' => ['communications', 'identityaccess', 'merchant'],

];
