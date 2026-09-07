<?php

return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'sync' => ['driver' => 'sync'],

        'database' => [
            'driver' => 'database',
            // Queue infrastructure stays central. Stancl records the originating
            // tenant ID in tenant-aware payloads and restores that context when
            // a worker processes the job. Waiting for commit avoids retaining a
            // central queue row for tenant work that ultimately rolls back.
            'connection' => env('DB_QUEUE_CONNECTION', env('DB_CONNECTION', 'sqlite')),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            // Tenant provisioning/deletion may spend up to several minutes in
            // cPanel and database operations. Keep retry_after comfortably above
            // the job timeout so another worker cannot pick up the same job.
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 900),
            'after_commit' => true,
        ],
    ],

    'batching' => [
        'database' => env('DB_QUEUE_CONNECTION', env('DB_CONNECTION', 'sqlite')),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_QUEUE_CONNECTION', env('DB_CONNECTION', 'sqlite')),
        'table' => 'failed_jobs',
    ],
];
