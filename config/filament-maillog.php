<?php

return [
    'amazon-ses' => [
        'configuration-set' => null,
    ],

    'resources' => [
        'MaiLogResource' => \App\Filament\Resources\MailLogResource::class,
    ],

    'navigation' => [
        'group' => 'Developer',
        'maillog' => [
            'label' => 'Mail Log',
            'register' => true,
            'sort' => 1,
            'icon' => 'heroicon-o-rectangle-stack',
        ],
    ],

    'sort' => [
        'column' => 'created_at',
        'direction' => 'desc',
    ],
];
