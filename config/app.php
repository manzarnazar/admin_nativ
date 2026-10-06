<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

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
    | application. If disabled, a simple generic error page is shown.
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
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inventory Lock Expiry Minutes
    |--------------------------------------------------------------------------
    |
    | This value determines how long (in minutes) an inventory lock remains
    | active before expiring. Used for both inventory locks and payment expiry.
    |
    */

    'inventory_lock_expiry_minutes' => env('INVENTORY_LOCK_EXPIRY_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Payment Retry Limit
    |--------------------------------------------------------------------------
    |
    | This value determines the maximum number of retry attempts allowed
    | for a failed payment before requiring user to contact support.
    |
    */

    'payment_retry_limit' => env('PAYMENT_RETRY_LIMIT', 5),

    'demo_mode' => (bool) env('DEMO_MODE', false),

    'demo_account_phone' => env('DEMO_ACCOUNT_PHONE', '9898765432'),

    // Independent of demo_mode above — gates ONLY App\Filament\Partner\Concerns\HasPartnerDemoGuard
    // (delete/edit blocking on the seeded partner-demo@gmail.com account). Defaults true (restricted)
    // so a missing/misconfigured env value fails safe on a live public demo; flip to false locally
    // to freely edit that account's content without also toggling demo_mode's app-wide effects
    // (PII masking, export-hiding, etc. everywhere else).
    'partner_demo_restricted' => (bool) env('PARTNER_DEMO_RESTRICTED', true),

    /*
    |--------------------------------------------------------------------------
    | Require Full Payment For Discounted Bookings
    |--------------------------------------------------------------------------
    |
    | Commission is always charged on the full undiscounted base_amount, but
    | Pay at Property / partial payment only ever collect a fraction of the
    | (possibly discounted) total online — for a discounted multi-mode,
    | partner-owned booking that can leave the amount collected online short
    | of the commission owed. When true, BookingService rejects combining a
    | resolved discount with Pay at Property or partial payment, and the
    | quote API hides those options once a discount resolves. Set to false to
    | go back to allowing any payment method with a discount and let admin
    | reconcile any resulting wallet shortfall manually instead.
    |
    */

    'require_full_payment_for_discounted_bookings' => (bool) env('REQUIRE_FULL_PAYMENT_FOR_DISCOUNTED_BOOKINGS', true),

];
