<?php

return [
    'statuses' => [
        'ok' => 'OK',
        'warning' => 'Warning',
        'error' => 'Error',
    ],

    'checks' => [
        'php_version' => [
            'label' => 'PHP version',
            'failed' => 'PHP :version is too old: the app needs PHP :minimum or newer',
        ],
        'php_extensions' => [
            'label' => 'PHP extensions',
            'failed' => 'missing: :extensions',
        ],
        'app_key' => [
            'label' => 'Application key (APP_KEY)',
            'failed' => 'run `php artisan key:generate`',
        ],
        'app_url' => [
            'label' => 'Application URL (APP_URL)',
            'failed' => 'set it to the public address: the links in the emails use it',
        ],
        'https' => [
            'label' => 'Secure connection (HTTPS)',
            'failed' => 'APP_URL does not start with https://: the browsers can install the app and receive the push notifications only over HTTPS',
            'invalid' => 'the certificate is not valid, the browsers will show a warning: :error',
            'expiring' => 'the certificate expires on :date: renew it',
            'valid' => 'certificate valid until :date',
        ],
        'debug' => [
            'label' => 'Debug mode (APP_DEBUG)',
            'failed' => 'turn it off in production: errors would show internal details',
        ],
        'timezone' => [
            'label' => 'Timezone (APP_TIMEZONE)',
        ],
        'cron_token' => [
            'label' => 'Queue and scheduler endpoints (ARTISAN_CRON_TOKEN)',
            'enabled' => 'enabled',
            'disabled' => 'disabled (only needed without a shell)',
        ],
        'mail' => [
            'label' => 'Email (MAIL_MAILER, MAIL_FROM_ADDRESS)',
            'failed' => 'the notification emails cannot be sent',
        ],
        'push' => [
            'label' => 'Push notifications (VAPID_*)',
            'failed' => 'the push notifications are off: run `php artisan webpush:vapid`',
        ],
        'initial_root' => [
            'label' => 'Initial root user (INITIAL_ROOT_*)',
            'failed' => 'needed by `och:install` on a new installation, and by `och:recover-root`',
        ],
        'database' => [
            'label' => 'Database connection',
        ],
        'migrations' => [
            'label' => 'Migrations',
            'empty' => 'the database is empty: run `php artisan och:post-deploy`',
            'failed' => ':count pending: run `php artisan och:post-deploy`',
        ],
        'storage_link' => [
            'label' => 'Public storage link (public/storage)',
            'failed' => 'missing: the uploaded files cannot be shown, run `php artisan storage:link`',
        ],
        'passport_keys' => [
            'label' => 'Keys of the AI assistants',
            'failed' => 'missing: the AI assistants cannot connect, run `php artisan och:install`',
        ],
        'writable_storage' => [
            'label' => 'Writable storage',
            'failed' => 'storage/framework and storage/logs must be writable',
        ],
        'env_file' => [
            'label' => 'Writable .env file',
            'failed' => 'the setup wizard cannot write the missing values: set them in .env yourself',
        ],
        'root_user' => [
            'label' => 'Root user (ID 1)',
            'failed' => 'missing: run `php artisan och:install`',
        ],
        'root_can_login' => [
            'label' => 'Root user can log in',
            'failed' => 'login disabled: run `php artisan och:recover-root` if nobody else can manage the users',
        ],
        'root_two_factor' => [
            'label' => 'Two-factor authentication of the root user',
            'failed' => 'recommended: the root user can do everything, set it up from its profile',
        ],
        'super_admins' => [
            'label' => 'Super administrators',
            'failed' => 'nobody can manage the application: run `php artisan och:install`',
        ],
    ],
];
