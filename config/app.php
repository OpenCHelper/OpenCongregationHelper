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

    'name' => env('APP_NAME', 'OpenCongregationHelper'),
    'version' => env('APP_VERSION', json_decode(file_get_contents(base_path('./composer.json')))->version),
    'version_info' => [
        'branch_file' => env('APP_VERSION_BRANCH_FILE', '.git/HEAD'),
        'commit_file' => env('APP_VERSION_COMMIT_FILE'),
    ],
    'repository_url' => 'https://github.com/OpenCHelper/OpenCongregationHelper',

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

    'timezone' => env('APP_TIMEZONE', 'UTC'),

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

    'available_locales' => [
        'en' => 'English',
        'it' => 'Italiano',
        'es' => 'Español',
        // 'fr' => 'Français',
        // 'de' => 'Deutsch',
        // 'pt' => 'Português',
        // 'ru' => 'Русский',
    ],

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
    | Artisan Cron Token
    |--------------------------------------------------------------------------
    |
    | Secret that external cron services must send in the `X-Cron-Token`
    | header to trigger the queue and scheduler endpoints. When empty,
    | those endpoints reject every request.
    |
    */

    'artisan_cron_token' => env('ARTISAN_CRON_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Personal Data On Shared Pages
    |--------------------------------------------------------------------------
    |
    | When enabled, publicly shared pages (guest panel) also display personal
    | data such as the assignee's full name and the territory notes.
    |
    */

    'enable_shared_pii' => (bool) env('ENABLE_SHARED_PII', false),

    /*
    |--------------------------------------------------------------------------
    | Initial Root User
    |--------------------------------------------------------------------------
    |
    | Credentials of the root user created by the database seeder when the
    | database contains no users yet.
    |
    */

    'initial_root_user' => [
        'name' => env('INITIAL_ROOT_USERNAME'),
        'email' => env('INITIAL_ROOT_USERMAIL'),
        'password' => env('INITIAL_ROOT_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo: Pre-filled Login Credentials
    |--------------------------------------------------------------------------
    |
    | When enabled, the login page is pre-filled with the credentials of the
    | root user, so that a demo can be tested right away (e.g. PR previews).
    | It MUST be enabled on demo installations with dummy data only, since
    | anyone could log in as root. It is always ignored in production.
    |
    */

    'demo_prefill_login_credentials' => (bool) env('DEMO_PREFILL_LOGIN_CREDENTIALS', false),

];
