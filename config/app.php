<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider; // For Laravel 10+

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Supermarket Billing System'), // Use env() for APP_NAME

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's maintenance mode status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Name (Custom from your old config)
    |--------------------------------------------------------------------------
    |
    | You can define your custom session name here.
    | It's better to set this in config/session.php 'cookie' option or via .env
    | but for direct porting, this is an option.
    | Note: Laravel's session configuration is primarily in config/session.php.
    | This is just a custom value you might use.
    */
    // 'session_name' => env('SESSION_NAME', 'BILLING_SESSION'), // Example, better in config/session.php


    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Laravel Framework Service Providers...
         */
        // Illuminate\Auth\AuthServiceProvider::class, // Usually default
        // Illuminate\Broadcasting\BroadcastServiceProvider::class, // Usually default
        // Illuminate\Bus\BusServiceProvider::class, // Usually default
        // Illuminate\Cache\CacheServiceProvider::class, // Usually default
        // Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class, // Usually default
        // Illuminate\Cookie\CookieServiceProvider::class, // Usually default
        // Illuminate\Database\DatabaseServiceProvider::class, // Usually default
        // Illuminate\Encryption\EncryptionServiceProvider::class, // Usually default
        // Illuminate\Filesystem\FilesystemServiceProvider::class, // Usually default
        // Illuminate\Foundation\Providers\FoundationServiceProvider::class, // Usually default
        // Illuminate\Hashing\HashServiceProvider::class, // Usually default
        // Illuminate\Mail\MailServiceProvider::class, // Usually default
        // Illuminate\Notifications\NotificationServiceProvider::class, // Usually default
        // Illuminate\Pagination\PaginationServiceProvider::class, // Usually default
        // Illuminate\Pipeline\PipelineServiceProvider::class, // Usually default
        // Illuminate\Queue\QueueServiceProvider::class, // Usually default
        // Illuminate\Redis\RedisServiceProvider::class, // Usually default
        // Illuminate\Auth\Passwords\PasswordResetServiceProvider::class, // Usually default
        // Illuminate\Session\SessionServiceProvider::class, // Usually default
        // Illuminate\Translation\TranslationServiceProvider::class, // Usually default
        // Illuminate\Validation\ValidationServiceProvider::class, // Usually default
        // Illuminate\View\ViewServiceProvider::class, // Usually default

        /*
         * Package Service Providers...
         */
        Jenssegers\Mongodb\MongodbServiceProvider::class,
        Laravel\Sanctum\SanctumServiceProvider::class,
        // Laravel\Socialite\SocialiteServiceProvider::class, // If you add Socialite
        // ... other package providers

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\FortifyServiceProvider::class, // If using Fortify (Breeze might use it)
        App\Providers\RouteServiceProvider::class,
        // App\Providers\TelescopeServiceProvider::class, // If using Telescope

    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
        // 'Mongo' => Jenssegers\Mongodb\MongodbServiceProvider::class, // Optional alias
    ])->toArray(),

];
