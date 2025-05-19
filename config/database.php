<?php

return [
    'default' => env('DB_CONNECTION', 'mongodb'),

    'connections' => [
        // ... (sqlite, mysql, etc. can be kept or removed) ...

        'mongodb' => [
            'driver'   => 'mongodb',
            'dsn'      => env('MONGO_DB_URI'), // Example: mongodb://username:password@host:port/auth_db?options
            'database' => env('MONGO_DB_DATABASE', 'billing_refactored'),
            // 'options' => [
            // 'replicaSet' => env('MONGO_REPLICA_SET_NAME'), // if using replica set
            // 'serverSelectionTimeoutMS' => env('MONGO_SERVER_SELECTION_TIMEOUT_MS', 5000),
            // 'connectTimeoutMS' => env('MONGO_CONNECT_TIMEOUT_MS', 10000),
            // ],
        ],
    ],

    'migrations' => 'migrations', // Standard Laravel, though less used with MongoDB typically

    // ... (Redis config if used) ...
];