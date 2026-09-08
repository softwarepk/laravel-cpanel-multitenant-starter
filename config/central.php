<?php

return [
    'force_https' => filter_var(env('FORCE_HTTPS', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOL),

    'platform' => [
        'domain' => strtolower(trim((string) env('TENANT_PLATFORM_DOMAIN', ''))),
        'document_root' => trim((string) env('TENANT_PLATFORM_DOCUMENT_ROOT', '')),
    ],

    'custom_domain' => [
        'dns_target' => strtolower(trim((string) env('CUSTOM_DOMAIN_DNS_TARGET', env('TENANT_PLATFORM_DOMAIN', '')))),
    ],

    'lifecycle' => [
        // A synchronous cPanel request may continue after the browser leaves.
        // Do not start a second provisioning/deletion attempt until the saved
        // operation has stopped reporting progress for this long.
        'operation_stale_after_seconds' => 300,
    ],

    'cpanel' => [
        'tenant_database_prefix' => env('CPANEL_TENANT_DB_PREFIX', ''),
        'api_host' => env('CPANEL_API_HOST'),
        'api_port' => (int) env('CPANEL_API_PORT', 2083),
        'api_user' => env('CPANEL_API_USER'),
        'api_token' => env('CPANEL_API_TOKEN'),
        'api_connect_timeout' => (int) env('CPANEL_API_CONNECT_TIMEOUT', 5),
        'api_timeout' => (int) env('CPANEL_API_TIMEOUT', 90),
    ],
];
