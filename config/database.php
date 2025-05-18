<?php // config/database.php

return [
    'mongodb' => [
        'uri' => $_ENV['MONGODB_URI'] ?? (defined('MONGODB_URI_CONFIG') ? MONGODB_URI_CONFIG : 'mongodb://localhost:27017'),
        'database_name' => 'billing_refactored', // Choose your DB name
        'options' => [], // MongoDB URI options
        'driver_options' => [ // MongoDB driver options
             'serverSelectionTimeoutMS' => 5000,
             'connectTimeoutMS' => 10000,
             // Add SSL context options if needed, especially if php.ini settings are not picked up
             // 'tls' => true, // This is usually implied by mongodb+srv
             // 'tlsCAFile' => 'C:\path\to\your\cacert.pem', // Example: uncomment and set path if needed
             // 'tlsAllowInvalidCertificates' => false, // Should always be false in production
        ],
    ],
];
