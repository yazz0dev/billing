<?php
return [
    // ...
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'sanctum' => [ // For API authentication
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent', // This will use Jenssegers' Eloquent MongoDB driver
            'model' => App\Models\User::class,
        ],
    ],
    // ... (password reset settings etc.)
];