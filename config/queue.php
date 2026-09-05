<?php

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => ['driver' => 'sync'],

        'database' => [
            'driver' => 'database',
            // Queue infrastructure stays central. Stancl records the originating
            // tenant ID in tenant-aware payloads and restores that context when
            // a worker processes the job.
            'connection' => env('DB_QUEUE_CONNECTION', env('DB_CONNECTION', 'sqlite')),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
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
