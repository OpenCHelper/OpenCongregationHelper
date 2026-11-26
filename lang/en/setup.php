<?php

return [
    'title' => 'Set up the app',
    'subheading' => 'A few steps to get the app ready for the congregation. Everything can be changed later from the settings.',
    'complete' => 'Complete the setup',

    'steps' => [
        'installation' => [
            'label' => 'Installation',
            'description' => 'Check the server',
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
        'all_ok' => 'The installation looks fine.',
        'problems' => '{1} :count check needs attention: the app works, but fix it when you can.|[2,*] :count checks need attention: the app works, but fix them when you can.',
        'check_again' => 'Check again',
        'storage_link' => 'Create the storage link',
        'passport_keys' => 'Create the keys of the AI assistants',
        'fixed' => 'Done.',
        'fix_failed' => 'It did not work: run `:command` on the server.',
        'test_email' => [
            'label' => 'Send a test email',
            'subject' => 'Test email from :app',
            'body' => 'This email was sent from the setup of :app: the emails of the app reach their recipients.',
            'sent' => 'Test email sent to :email.',
            'sent_body' => 'If it does not arrive in a few minutes, check the spam folder and the email settings of the server (MAIL_*).',
            'failed' => 'The test email could not be sent.',
        ],
    ],

    'administrators' => [
        'intro' => 'Add the people who will manage the app with you: they become super administrators and receive an email to choose their password. You (:email) already are one.',
        'add' => 'Add an administrator',
    ],

    'features' => [
        'sharing' => 'Sharing of assignments',
        'sharing_description' => 'Whether assignments can be shared through a public link, with those without an account.',
    ],

    'skip' => [
        'label' => 'Skip',
        'heading' => 'Skip the setup?',
        'description' => 'The wizard will not be offered again: everything it sets can be changed later from the settings and the users.',
        'confirm' => 'Skip the setup',
    ],

    'completed' => [
        'title' => 'The app is set up',
        'body' => '{0} You can change these choices later from the settings.|{1} :count administrator was invited by email.|[2,*] :count administrators were invited by email.',
    ],

    'banner' => [
        'text' => 'The app is not set up yet: choose its name, its administrators and the features to enable.',
        'action' => 'Set up the app',
    ],
];
