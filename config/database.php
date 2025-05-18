<?php // config/database.php

return [
    'mongodb' => [
        'uri' => $_ENV['MONGODB_URI'] ?? (defined('MONGODB_URI_CONFIG') ? MONGODB_URI_CONFIG : 'mongodb://localhost:27017'),
        'database_name' => 'billing_refactored', // Choose your DB name
        'options' => [], // MongoDB URI options
        'driver_options' => [ // MongoDB driver options
             'serverSelectionTimeoutMS' => 5000,
             'connectTimeoutMS' => 10000,
        ],
    ],
];
