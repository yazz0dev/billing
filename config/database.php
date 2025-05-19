<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'mongodb'),
    'connections' => [
        'mongodb' => [
            'driver'   => 'mongodb',
            'dsn'      => env('MONGO_DB_URI'),
            'database' => env('MONGO_DB_DATABASE', 'billing_refactored'),
            // You can add options directly here if needed, or rely on the DSN
            // 'options' => [
            //     'serverSelectionTimeoutMS' => env('MONGO_SERVER_SELECTION_TIMEOUT_MS', 5000),
            //     'connectTimeoutMS' => env('MONGO_CONNECT_TIMEOUT_MS', 10000),
            // ],
        ],
        // ... other default connections like sqlite, mysql, pgsql, sqlsrv (can be removed if not used)
    ],
    'migrations' => 'migrations',
    // ... Redis config ...
];