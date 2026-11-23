<?php

return [
    'install' => [
        'recommended' => 'Recommended',
        'title' => 'Install :app',
        'description' => 'It is the best way to use :app: it works like any other app on your device.',
        'benefits' => [
            'home_screen' => [
                'title' => 'Always at hand',
                'description' => 'Open it from your home screen or desktop, like any other app.',
            ],
            'own_window' => [
                'title' => 'More room',
                'description' => 'It opens in its own window, without the address bar and tabs of the browser.',
            ],
            'ios_notifications' => [
                'title' => 'Notifications on iPhone and iPad',
                'description' => 'There, the notifications of the app work only once it is installed.',
            ],
        ],
        'ios_steps_before' => 'Tap',
        'ios_share' => 'Share',
        'ios_steps_after' => 'then "Add to Home Screen" to open it like any other app.',
        'action' => 'Install',
        'later' => 'You can install it later from the user menu or your profile, where you can also turn on the notifications.',
        'menu_label' => 'Install app',
        'profile' => [
            'title' => 'Install the app',
            'description' => 'Open it from your home screen or desktop, in its own window, like any other app.',
            'installed' => 'The app is installed on this device.',
        ],
        'dismiss' => 'Not now',
    ],
    'notifications' => [
        'title' => 'Turn on notifications',
        'description' => 'Get the notifications of :app right on this device, even when the app is closed.',
        'benefits' => [
            'assignments' => [
                'title' => 'Territory assignments',
                'description' => 'Know right away when a territory is assigned to you.',
            ],
            'reminders' => [
                'title' => 'Reminders',
                'description' => 'Be reminded of the territories you have had for a long time.',
            ],
            'campaigns' => [
                'title' => 'Campaigns',
                'description' => 'Hear about new campaigns as soon as they start.',
            ],
        ],
        'action' => 'Turn on notifications',
        'dismiss' => 'Not now',
        'later' => 'Your browser will ask you to allow them. You can turn them off at any time from your profile.',
    ],
    'offline' => [
        'title' => 'You are offline',
        'description' => 'This page cannot be loaded without an internet connection. Check your connection and try again.',
        'retry' => 'Try again',
    ],
];
