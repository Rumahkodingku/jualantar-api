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
        'invalid_credentials' => ['status' => 401, 'title' => 'Unauthenticated'],
        'forbidden' => ['status' => 403, 'title' => 'Forbidden'],
        'email_not_verified' => ['status' => 403, 'title' => 'Forbidden'],
        'privilege_escalation' => ['status' => 403, 'title' => 'Forbidden'],
        'not_found' => ['status' => 404, 'title' => 'Not Found'],
        'method_not_allowed' => ['status' => 405, 'title' => 'Method Not Allowed'],
        'conflict' => ['status' => 409, 'title' => 'Conflict'],
        'role_in_use' => ['status' => 409, 'title' => 'Conflict'],
        'protected_role' => ['status' => 409, 'title' => 'Conflict'],
        'permission_in_use' => ['status' => 409, 'title' => 'Conflict'],
        'role_already_exists' => ['status' => 409, 'title' => 'Conflict'],
        'permission_already_exists' => ['status' => 409, 'title' => 'Conflict'],
        'unsupported_media_type' => ['status' => 415, 'title' => 'Unsupported Media Type'],
        'validation_error' => ['status' => 422, 'title' => 'Validation Failed'],
        'unprocessable_entity' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'invalid_role' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'invalid_permission' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'invalid_verification' => ['status' => 403, 'title' => 'Forbidden'],
        'object_not_found' => ['status' => 404, 'title' => 'Not Found'],
        'merchant_registration_not_found' => ['status' => 404, 'title' => 'Not Found'],
        'merchant_registration_already_exists' => ['status' => 409, 'title' => 'Conflict'],
        'merchant_not_found' => ['status' => 404, 'title' => 'Not Found'],
        'outlet_not_found' => ['status' => 404, 'title' => 'Not Found'],
        'outlet_scope_forbidden' => ['status' => 403, 'title' => 'Forbidden'],
        'outlet_capability_forbidden' => ['status' => 403, 'title' => 'Forbidden'],
        'invalid_status_transition' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'invalid_operating_hours' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'invalid_service_area' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'duplicate_outlet_assignment' => ['status' => 409, 'title' => 'Conflict'],
        'user_not_found' => ['status' => 404, 'title' => 'Not Found'],
        'merchant_application_not_found' => ['status' => 404, 'title' => 'Not Found'],
        'merchant_approval_not_found' => ['status' => 404, 'title' => 'Not Found'],
        'notification_not_found' => ['status' => 404, 'title' => 'Not Found'],
        'invalid_registration_state' => ['status' => 409, 'title' => 'Conflict'],
        'invalid_state_transition' => ['status' => 409, 'title' => 'Conflict'],
        'not_assigned' => ['status' => 403, 'title' => 'Forbidden'],
        'business_rule_violation' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'registration_incomplete' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'invalid_service' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'invalid_category' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'invalid_geography' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'invalid_payout_account' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'upload_invalid' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'rate_limited' => ['status' => 429, 'title' => 'Too Many Requests'],
        'internal_server_error' => ['status' => 500, 'title' => 'Internal Server Error'],
        'storage_error' => ['status' => 500, 'title' => 'Storage Error'],
        'communication_invalid_recipient' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'communication_template_not_found' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'communication_provider_rejected' => ['status' => 422, 'title' => 'Unprocessable Entity'],
        'communication_duplicate' => ['status' => 409, 'title' => 'Conflict'],
        'communication_provider_unavailable' => ['status' => 503, 'title' => 'Service Unavailable'],
        'communication_configuration_error' => ['status' => 500, 'title' => 'Internal Server Error'],
        'communication_delivery_failed' => ['status' => 502, 'title' => 'Bad Gateway'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    |
    | Controls the HTTP request logging and metrics foundation. Request IDs,
    | exception rendering, and problem details are independent of this flag.
    |
    */

    'observability' => [
        'enabled' => env('OBSERVABILITY_ENABLED', true),
        'metrics_adapter' => env('PROMETHEUS_METRICS_ADAPTER', 'in_memory'),
    ],

];
