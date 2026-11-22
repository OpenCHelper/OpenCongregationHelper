<?php

return [
    'install' => [
        'recommended' => 'Consigliato',
        'title' => 'Installa :app',
        'description' => 'È il modo migliore per usare :app: ricevi le sue notifiche direttamente sul tuo dispositivo.',
        'benefits' => [
            'notifications' => [
                'title' => 'Notifiche sul tuo dispositivo',
                'description' => 'Sappi subito quando ti viene assegnato un territorio o quando qualcosa richiede la tua attenzione.',
            ],
            'home_screen' => [
                'title' => 'Sempre a portata di mano',
                'description' => 'Aprila dalla schermata Home o dal desktop, in una sua finestra, come qualsiasi altra app.',
            ],
        ],
        'ios_steps_before' => 'Tocca',
        'ios_share' => 'Condividi',
        'ios_steps_after' => 'e poi "Aggiungi alla schermata Home" per aprirla come qualsiasi altra app.',
        'action' => 'Installa',
        'later' => 'Puoi anche attivare le notifiche più tardi dal tuo profilo.',
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
