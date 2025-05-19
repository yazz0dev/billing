<?php

use Illuminate\Support\Facades\Facade;

return [
    // ... (standard Laravel app config values) ...
    'name' => env('APP_NAME', 'Laravel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    // Add jenssegers/mongodb service provider
    'providers' => ServiceProvider::defaultProviders()->merge([
        // ...
        Jenssegers\Mongodb\MongodbServiceProvider::class,
        // ...
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        // ...
        'Mongo' => Jenssegers\Mongodb\MongodbServiceProvider::class, // Optional alias
    ])->toArray(),
    // ... (rest of standard Laravel app config) ...
];
return [
    'name' => 'Supermarket Billing System',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['DEBUG_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'session_name' => 'BILLING_SESSION',
];
