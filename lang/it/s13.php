<?php

return [
    'action' => [
        'label' => 'S-13',
        'modal_heading' => 'Registro delle assegnazioni dei territori (S-13)',
        'modal_description' => 'Scarica l\'S-13 di un anno di servizio, compilato con le assegnazioni dei territori sul modulo ufficiale.',
        'submit' => 'Scarica',
    ],
    'fields' => [
        'service_year' => [
            'label' => 'Anno di servizio',
            'helper' => 'Un anno di servizio va dal 1° settembre al 31 agosto.',
            'option' => ':year (settembre :from – agosto :to)',
        ],
        'template' => [
            'label' => 'Modulo S-13 ufficiale',
            'replace_label' => 'Sostituisci il modulo S-13 ufficiale',
            'helper' => 'Il PDF dell\'S-13 (Registro delle assegnazioni dei territori), scaricato da JW Hub, nella lingua della congregazione.',
            'invalid' => 'Questo file non è un PDF utilizzabile: carica l\'S-13 scaricato da JW Hub.',
        ],
    ],
    'template' => [
        'missing_heading' => 'Il modulo S-13 ufficiale non è ancora stato caricato',
        'missing_admin' => 'Carica l\'S-13 scaricato da JW Hub: verrà conservato per i prossimi download.',
        'missing_user' => 'Chiedi a un super amministratore di caricare l\'S-13 scaricato da JW Hub.',
    ],
    'structure' => [
        'stored_heading' => 'Il modulo S-13 caricato è diverso da quello per cui è stata fatta l\'app',
        'uploaded_heading' => 'Questo modulo S-13 è diverso da quello per cui è stata fatta l\'app',
        'description' => 'Probabilmente è una nuova edizione del modulo: il registro potrebbe essere scritto nei punti sbagliati. Segnalalo, allegando il modulo, così che l\'app venga aggiornata. Differenze:',
        'report' => 'Segnala su GitHub',
        'accept' => 'Usa comunque questo modulo',
        'accept_required' => 'Conferma che questo modulo va usato anche se è diverso da quello previsto.',
        'pages' => 'Pagine: :actual invece di :expected',
        'size' => 'Dimensioni della pagina: :actual invece di :expected',
        'missing_fields' => 'Campi mancanti: :fields',
        'added_fields' => 'Campi nuovi: :fields',
        'grid' => 'Le linee della tabella sono in punti diversi',
        'and_more' => 'e altri :count',
    ],
];
