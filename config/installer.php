<?php

return [
    'complete' => filter_var(env('INSTALLATION_COMPLETE', false), FILTER_VALIDATE_BOOL),
    'legacy_configured' => null,
    'pending_file' => storage_path('app/installation.pending'),
    'complete_file' => storage_path('app/installation.complete'),
    'queue_php_binary' => env('INSTALLER_QUEUE_PHP_BINARY'),
    'queue_flock_binary' => env('INSTALLER_QUEUE_FLOCK_BINARY'),
];
