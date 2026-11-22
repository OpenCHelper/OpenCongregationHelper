<?php

return [
    'install' => [
        'recommended' => 'Recommended',
        'title' => 'Install :app',
        'description' => 'It is the best way to use :app: you get its notifications right on your device.',
        'benefits' => [
            'notifications' => [
                'title' => 'Notifications on your device',
                'description' => 'Know right away when a territory is assigned to you or something needs your attention.',
            ],
            'home_screen' => [
                'title' => 'Always at hand',
                'description' => 'Open it from your home screen or desktop, in its own window, like any other app.',
            ],
        ],
        'ios_steps_before' => 'Tap',
        'ios_share' => 'Share',
        'ios_steps_after' => 'then "Add to Home Screen" to open it like any other app.',
        'action' => 'Install',
        'dismiss' => 'Not now',
    ],
    'offline' => [
        'title' => 'You are offline',
        'description' => 'This page cannot be loaded without an internet connection. Check your connection and try again.',
        'retry' => 'Try again',
    ],
];
