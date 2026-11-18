<?php

return [
    'navigation_label' => 'Impostazioni',
    'default' => 'Predefinito: :value.',
    'on' => 'attivo',
    'off' => 'disattivo',

    'units' => [
        'days_before_end' => 'giorni prima della fine',
        'days_after_assignment' => 'giorni dopo l\'assegnazione',
    ],

    'stages' => [
        'active_note' => 'finché non è in scadenza',
        'terminated_note' => 'dalla data di fine',
        'in_progress' => 'In corso',
        'in_progress_note' => 'dal giorno dell\'assegnazione',
        'end_date' => 'Data di fine',
        'assigned' => 'Assegnata',
    ],

    'pages' => [
        'general' => 'Generali',
        'territories' => 'Territori',
        'campaigns' => 'Campagne',
        'notifications' => 'Notifiche',
    ],

    'sections' => [
        'campaign_expiry' => 'Scadenza delle campagne',
        'campaign_expiry_description' => 'Man mano che si avvicina la data di fine, una campagna attiva diventa in scadenza e poi quasi scaduta.',
        'application' => 'Applicazione',
        'appearance' => 'Aspetto',
        'maps_and_sharing' => 'Mappe e condivisione',
        'stale_assignments' => 'Assegnazioni fuori da troppo tempo',
        'stale_assignments_description' => 'Le assegnazioni ancora in corso dopo questi giorni sono fuori da troppo tempo: una volta a settimana, ai loro assegnatari viene ricordato di restituirle.',
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
        'logo' => [
            'label' => 'Logo',
            'helper' => 'Mostrato nella barra in alto al posto del nome. Al massimo 2 MB.',
        ],
        'primary_color' => [
            'label' => 'Colore principale',
            'helper' => 'Colore dei pulsanti, dei link e degli elementi selezionati, per gli utenti che non ne hanno scelto uno nelle proprie preferenze dell\'interfaccia.',
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
        'stale_after_days' => [
            'label' => 'Fuori da troppo tempo',
        ],
        'dead_after_days' => [
            'label' => 'Persa',
        ],
        'stale_check_day' => [
            'label' => 'Giorno dei promemoria',
            'helper' => 'Dal lunedì al venerdì.',
        ],
        'stale_check_time' => [
            'label' => 'Ora dei promemoria',
            'helper' => 'Ora di Roma, dalle 8:00 alle 22:00.',
        ],
        'mail_from_name' => [
            'label' => 'Nome del mittente delle email',
            'helper' => 'Mostrato come mittente delle email inviate dall\'applicazione.',
        ],
    ],
];
