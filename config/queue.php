<?php

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => ['driver' => 'sync'],

        // Kept as an opt-in connection for applications that later choose to
        // introduce background workers. The starter itself does not require one.
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION', env('DB_CONNECTION', 'sqlite')),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
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
