<?php

return [
    'navigation_label' => 'Impostazioni',
    'default' => 'Predefinito: :value.',
    'on' => 'attivo',
    'off' => 'disattivo',

    'pages' => [
        'general' => 'Generali',
        'territories' => 'Territori',
    ],

    'fields' => [
        'name' => [
            'label' => 'Nome dell\'applicazione',
        ],
        'locale' => [
            'label' => 'Lingua predefinita',
        ],
        'timezone' => [
            'label' => 'Fuso orario',
        ],
        'map_default_center' => [
            'label' => 'Centro predefinito delle mappe',
            'helper' => 'Dove si aprono le mappe finché nessun territorio ha una mappa, come "latitudine, longitudine".',
            'invalid' => 'Scrivi le coordinate come "latitudine, longitudine" (es. 41.159063, -74.255188).',
        ],
        'shared_pii' => [
            'label' => 'Mostra i dati personali nelle pagine condivise',
            'helper' => 'La pagina pubblica di un\'assegnazione condivisa mostra anche il nome dell\'assegnatario e le note del territorio.',
        ],
    ],
];
