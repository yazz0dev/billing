// In config/database.php

'default' => env('DB_CONNECTION', 'mongodb'),

'connections' => [
    // ... other SQL examples (can be removed if not used) ...

    'mongodb' => [
        'driver'   => 'mongodb',
        'dsn'      => env('MONGO_DB_URI'), // Using DSN directly
        'database' => env('MONGO_DB_DATABASE', 'billing_refactored'),
        // 'options' => [
        //     'serverSelectionTimeoutMS' => env('MONGO_SERVER_SELECTION_TIMEOUT_MS', 5000),
        //     'connectTimeoutMS' => env('MONGO_CONNECT_TIMEOUT_MS', 10000),
        // ],
        // 'driver_options' => [],
    ],
],