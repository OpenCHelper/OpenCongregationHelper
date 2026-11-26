<?php

return [
    'title' => 'Install the app',
    'subheading' => 'A few steps to get the app ready for the congregation. Everything chosen after the root user can be changed later from the settings.',
    'complete' => 'Install',

    'token' => [
        'missing_heading' => 'Add the setup token',
        'missing_description' => 'The app is not installed yet. So that nobody else can install it first, add a setup token of your choice to the .env file on the server, e.g. :example, then reload this page.',
        'description' => 'Type the setup token, the value of SETUP_TOKEN in the .env file on the server.',
        'label' => 'Setup token',
        'submit' => 'Continue',
        'invalid' => 'The setup token is not correct.',
        'throttled' => 'Too many attempts: try again in :seconds seconds.',
    ],

    'steps' => [
        'requirements' => [
            'label' => 'Server',
            'description' => 'Requirements',
        ],
        'environment' => [
            'label' => 'Configuration',
            'description' => 'The .env file',
        ],
        'tasks' => [
            'label' => 'Background tasks',
            'description' => 'Scheduler and queue',
        ],
        'database' => [
            'label' => 'Database',
            'description' => 'Create the tables',
        ],
        'root' => [
            'label' => 'Root user',
            'description' => 'Your account',
        ],
        'congregation' => [
            'label' => 'Congregation',
            'description' => 'Name and appearance',
        ],
        'administrators' => [
            'label' => 'Administrators',
            'description' => 'Who manages the app',
        ],
        'features' => [
            'label' => 'Features',
            'description' => 'What to enable',
        ],
    ],

    'checks' => [
        'all_ok' => 'The server meets the requirements.',
        'problems' => '{1} :count check needs attention.|[2,*] :count checks need attention.',
        'errors' => 'Fix the errors before going on, then check again.',
        'check_again' => 'Check again',
        'test_email' => [
            'label' => 'Send a test email',
            'subject' => 'Test email from :app',
            'body' => 'This email was sent from the installation of :app: the emails of the app reach their recipients.',
            'sent' => 'Test email sent to :email.',
            'sent_body' => 'If it does not arrive in a few minutes, check the spam folder and the email settings (MAIL_*).',
            'failed' => 'The test email could not be sent.',
        ],
    ],

    'environment' => [
        'already_set' => 'Already set in .env',
        'password_set' => 'Set in .env',
        'password_keep' => 'Set in .env: leave empty to keep it',
        'default_option' => 'Default: :value',
        'intro' => 'These values are missing from the .env file: they will be added to it. The values already set are never changed.',
        'nothing_missing' => 'All the values the app needs are already set in the .env file.',
        'not_writable' => 'The .env file cannot be written: add the missing values to it yourself, then reload this page.',
        'application' => 'Application',
        'app_url' => 'The public address of the app, used in the links of the emails.',
        'app_timezone' => 'Used for the dates and the time of the reminders.',
        'app_locale' => 'Language of the app for the users who did not choose one.',
        'database' => 'Database',
        'db_prefix' => 'Prefix of the names of the tables, to share the database with other applications (optional).',
        'db_sqlite' => 'The full path of the database file: it is created if it does not exist.',
        'mail' => 'Email',
        'mailers' => [
            'log' => 'Log: do not send, only write in the log',
            'array' => 'Array: do not send, only keep in memory (tests)',
            'failover' => 'Failover: try the mailers of the configuration in turn',
            'roundrobin' => 'Round robin: share the emails among the mailers of the configuration',
            'nativemailfx' => 'PHP mail() function',
        ],
        'keys' => 'Keys',
        'push_keys' => 'Push notifications (VAPID_*)',
        'passport_keys' => 'AI assistants',
        'push_not_writable' => 'The .env file cannot be written: create them with php artisan webpush:vapid.',
        'key_statuses' => [
            'set' => 'Already set',
            'created' => 'Created on going on',
            'unavailable' => 'Cannot be created',
        ],
        'keys_failed' => 'The keys could not be created (`:command`).',
        'mail_from_address' => 'The sender of the emails of the app.',
        'database_failed' => 'The app cannot connect to the database.',
        'write_failed' => 'The .env file could not be written.',
    ],

    'tasks' => [
        'intro' => 'Some work runs in the background: the scheduled tasks (e.g. the weekly reminders) and, with a queue, the sending of the emails. Set it up now or after the installation: the app works meanwhile, but these tasks do not run.',
        'queue_helper' => 'Where the emails and the other background work wait to be processed.',
        'with_shell' => 'With a shell',
        'without_shell' => 'Without a shell',
        'cron_intro' => 'Add this line to the crontab of the server (crontab -e), to run the scheduler every minute:',
        'worker_intro' => 'And this one to process the queue (or run php artisan queue:work as a long-running process, e.g. with Supervisor):',
        'sync_note' => 'With this queue nothing waits: the work is done right away, and no queue worker is needed.',
        'service_intro' => 'On a hosting without a shell, have an external cron service call these addresses every minute, with the POST method:',
        'token_intro' => 'Each call must send this header, which proves it comes from your service:',
        'token_missing' => 'The secret of these addresses (ARTISAN_CRON_TOKEN) is missing from .env, and could not be created: set it yourself.',
        'copy' => 'Copy',
        'queues' => [
            'sync' => 'Sync: right away, during the request (no worker needed)',
            'database' => 'Database: in the database, processed by a worker (recommended)',
        ],
    ],
    'database' => [
        'intro' => 'Going on creates the tables of the database and the permissions of the roles. It can take a minute.',
        'failed' => 'The database could not be set up (`:command`).',
    ],

    'root' => [
        'intro' => 'The root user can do everything, and can never be deleted: it is your account. Send a test email to check that the emails of the app arrive.',
        'password' => 'Password',
        'password_confirmation' => 'Confirm the password',
        'email_first' => 'Type a valid email address first.',
        'test_email_hint' => 'No test email sent yet: requiring the email confirmation needs one.',
        'mailer_not_sending' => 'The emails are not sent (MAIL_MAILER is :mailer): the test email reaches no inbox. Choose a mailer that sends them in the Configuration step to receive it.',
    ],

    'administrators' => [
        'intro' => 'Add the people who will manage the app with you: they become super administrators and receive an email to choose their password. You can also add them later.',
        'add' => 'Add an administrator',
        'root_email' => 'This is the email address of the root user.',
    ],

    'features' => [
        'sharing' => 'Sharing of assignments',
        'sharing_description' => 'Whether assignments can be shared through a public link, with those without an account.',
        'two_factor_root' => 'You will set it up right after logging in.',
        'email_verification_needs_test' => 'Send a test email first, from the Root user step: without working emails nobody could confirm their address.',
    ],

    'completed' => [
        'heading' => 'The app is installed',
        'description' => 'Log in as the root user. The setup token is no longer used: you can remove SETUP_TOKEN from the .env file.',
        'log_in' => 'Log in',
    ],
];
