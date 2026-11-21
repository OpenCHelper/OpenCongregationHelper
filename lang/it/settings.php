<?php

return [
    'navigation_label' => 'Impostazioni',
    'default' => 'Predefinito: :value.',

    'units' => [
        'days_before_end' => 'giorni prima della fine',
        'days_after_assignment' => 'giorni dopo l\'assegnazione',
        'assignments' => 'assegnazioni',
        'months' => 'mesi',
    ],

    'stages' => [
        'active_note' => 'finché non è in scadenza',
        'terminated_note' => 'dalla data di fine',
        'in_progress' => 'In corso',
        'in_progress_note' => 'dal giorno dell\'assegnazione',
        'end_date' => 'Data di fine',
        'assigned' => 'Assegnata',
        'low_frequency' => 'Bassa',
        'low_frequency_note' => 'meno assegnazioni, anche nessuna',
    ],

    'pages' => [
        'general' => 'Generali',
        'territories' => 'Territori',
        'campaigns' => 'Campagne',
    ],

    'sections' => [
        'campaign_expiry' => 'Scadenza delle campagne',
        'campaign_expiry_description' => 'Man mano che si avvicina la data di fine, una campagna attiva diventa in scadenza e poi quasi scaduta.',
        'appearance' => 'Aspetto',
        'congregation' => 'Congregazione',
        'maps' => 'Mappe',
        'shared_pages_privacy' => 'Privacy delle pagine condivise',
        'shared_pages_privacy_description' => 'Cosa mostra la pagina pubblica di un\'assegnazione condivisa a chiunque ne abbia il link.',
        'stale_assignments' => 'Assegnazioni fuori da troppo tempo',
        'stale_assignments_description' => 'Le assegnazioni ancora in corso dopo questi giorni sono fuori da troppo tempo: una volta a settimana, ai loro assegnatari viene ricordato di restituirle e il reparto territori ne riceve un riepilogo via email e nell\'app.',
        'assignment_frequency' => 'Frequenza di assegnazione',
        'assignment_frequency_description' => 'Quanto spesso viene assegnato ogni territorio, come colorato sulla mappa della dashboard: in base al numero delle sue assegnazioni negli ultimi mesi.',
    ],

    'fields' => [
        'kingdom_hall_address' => [
            'label' => 'Indirizzo della Sala del Regno',
            'placeholder' => 'Via, numero civico, CAP e città',
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
            'hide' => 'Nascondi i dati personali',
            'hide_description' => 'Solo il territorio, la sua mappa e le date: l\'assegnatario e le note restano nell\'app.',
            'show' => 'Mostra i dati personali',
            'show_description' => 'Anche il nome dell\'assegnatario e le note del territorio, a chiunque abbia il link. I link non scadono mai e possono essere inoltrati.',
            'recommended' => 'Consigliato',
            'confirm_heading' => 'Mostrare i dati personali nelle pagine condivise?',
            'consequences' => [
                'Chiunque abbia il link di un\'assegnazione condivisa vedrà il nome dell\'assegnatario e le note del territorio, che possono descrivere dei padroni di casa.',
                'I link condivisi non scadono mai e possono essere inoltrati, ad esempio in una chat di gruppo.',
                'Fallo solo se la congregazione è d\'accordo e le note non contengono dati personali.',
            ],
            'understood' => 'Ho capito che chiunque abbia un link condiviso vedrà questi dati, e la congregazione è d\'accordo.',
            'understood_required' => 'Conferma di aver capito cosa comporta mostrare i dati personali.',
            'confirm' => 'Mostra i dati personali',
        ],
        'stale_after_days' => [
            'label' => 'Fuori da troppo tempo',
        ],
        'dead_after_days' => [
            'label' => 'Persa',
        ],
        'stale_check_day' => [
            'label' => 'Giorno dei promemoria',
        ],
        'stale_check_time' => [
            'label' => 'Ora dei promemoria',
            'helper' => 'Nel fuso orario dell\'applicazione (`APP_TIMEZONE`). I promemoria vengono inviati solo se a quell\'ora lo scheduler è in esecuzione, come cron job o come processo sempre attivo (es. con Supervisor): vedi la guida al setup.',
        ],
        'stale_summary_recipients' => [
            'label' => 'Destinatari del riepilogo',
            'helper' => 'Chi nel reparto territori riceve il riepilogo settimanale delle assegnazioni fuori da troppo tempo, via email e nell\'app.',
            'members' => 'Tutti i membri del reparto',
            'overseers' => 'Solo i responsabili',
        ],
        'frequency_period_months' => [
            'label' => 'Mesi considerati',
            'helper' => 'Si contano le assegnazioni iniziate in questi ultimi mesi.',
        ],
        'medium_frequency_min_assignments' => [
            'label' => 'Media',
        ],
        'high_frequency_min_assignments' => [
            'label' => 'Frequente',
        ],
    ],
];
