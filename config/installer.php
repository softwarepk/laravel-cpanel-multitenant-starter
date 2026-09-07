<?php

return [
    'complete' => filter_var(env('INSTALLATION_COMPLETE', false), FILTER_VALIDATE_BOOL),
    'legacy_configured' => null,
    'pending_file' => storage_path('app/installation.pending'),
    'complete_file' => storage_path('app/installation.complete'),
];
