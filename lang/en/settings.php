<?php

return [
    'navigation_label' => 'Settings',
    'default' => 'Default: :value.',
    'on' => 'on',
    'off' => 'off',

    'pages' => [
        'general' => 'General',
        'territories' => 'Territories',
    ],

    'fields' => [
        'name' => [
            'label' => 'Name of the application',
        ],
        'locale' => [
            'label' => 'Default language',
        ],
        'timezone' => [
            'label' => 'Timezone',
        ],
        'map_default_center' => [
            'label' => 'Default map centre',
            'helper' => 'Where the maps open while no territory has a map yet, as "latitude, longitude".',
            'invalid' => 'Write the coordinates as "latitude, longitude" (e.g. 41.159063, -74.255188).',
        ],
        'shared_pii' => [
            'label' => 'Show personal data on shared pages',
            'helper' => 'The public page of a shared assignment also shows the name of the assignee and the notes of the territory.',
        ],
    ],
];
