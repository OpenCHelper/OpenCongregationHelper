<?php

return [
    'install' => [
        'recommended' => 'Consigliato',
        'title' => 'Installa :app',
        'description' => 'È il modo migliore per usare :app: funziona come qualsiasi altra app sul tuo dispositivo.',
        'benefits' => [
            'home_screen' => [
                'title' => 'Sempre a portata di mano',
                'description' => 'Aprila dalla schermata Home o dal desktop, come qualsiasi altra app.',
            ],
            'own_window' => [
                'title' => 'Più spazio',
                'description' => 'Si apre in una sua finestra, senza la barra degli indirizzi e le schede del browser.',
            ],
            'ios_notifications' => [
                'title' => 'Notifiche su iPhone e iPad',
                'description' => 'Lì le notifiche dell\'app funzionano solo dopo averla installata.',
            ],
        ],
        'ios_steps_before' => 'Tocca',
        'ios_share' => 'Condividi',
        'ios_steps_after' => 'e poi "Aggiungi alla schermata Home" per aprirla come qualsiasi altra app.',
        'action' => 'Installa',
        'later' => 'Puoi installarla più tardi dal menu utente o dal tuo profilo, dove puoi anche attivare le notifiche.',
        'menu_label' => 'Installa app',
        'profile' => [
            'title' => 'Installa l\'app',
            'description' => 'Aprila dalla schermata Home o dal desktop, in una sua finestra, come qualsiasi altra app.',
            'installed' => 'L\'app è installata su questo dispositivo.',
        ],
        'dismiss' => 'Non ora',
    ],
    'notifications' => [
        'title' => 'Attiva le notifiche',
        'description' => 'Ricevi le notifiche di :app direttamente su questo dispositivo, anche quando l\'app è chiusa.',
        'benefits' => [
            'assignments' => [
                'title' => 'Assegnazioni dei territori',
                'description' => 'Sappi subito quando ti viene assegnato un territorio.',
            ],
            'reminders' => [
                'title' => 'Promemoria',
                'description' => 'Ricevi un promemoria per i territori che hai da molto tempo.',
            ],
            'campaigns' => [
                'title' => 'Campagne',
                'description' => 'Scopri le nuove campagne non appena iniziano.',
            ],
        ],
        'action' => 'Attiva le notifiche',
        'dismiss' => 'Non ora',
        'later' => 'Il browser ti chiederà di consentirle. Puoi disattivarle in qualsiasi momento dal tuo profilo.',
    ],
    'offline' => [
        'title' => 'Sei offline',
        'description' => 'Questa pagina non può essere caricata senza una connessione a internet. Controlla la connessione e riprova.',
        'retry' => 'Riprova',
    ],
];
