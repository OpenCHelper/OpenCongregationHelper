<?php

return [
    'title' => 'Configura l\'app',
    'subheading' => 'Pochi passaggi per preparare l\'app per la congregazione. Tutto si può cambiare in seguito dalle impostazioni.',
    'complete' => 'Completa la configurazione',

    'steps' => [
        'installation' => [
            'label' => 'Installazione',
            'description' => 'Controlla il server',
        ],
        'congregation' => [
            'label' => 'Congregazione',
            'description' => 'Nome e aspetto',
        ],
        'administrators' => [
            'label' => 'Amministratori',
            'description' => 'Chi gestisce l\'app',
        ],
        'features' => [
            'label' => 'Funzionalità',
            'description' => 'Cosa attivare',
        ],
    ],

    'checks' => [
        'all_ok' => 'L\'installazione sembra a posto.',
        'problems' => '{1} :count controllo richiede attenzione: l\'app funziona, ma correggilo appena puoi.|[2,*] :count controlli richiedono attenzione: l\'app funziona, ma correggili appena puoi.',
        'check_again' => 'Controlla di nuovo',
        'storage_link' => 'Crea il collegamento dello storage',
        'passport_keys' => 'Crea le chiavi degli assistenti IA',
        'fixed' => 'Fatto.',
        'fix_failed' => 'Non ha funzionato: esegui `:command` sul server.',
        'test_email' => [
            'label' => 'Invia un\'email di prova',
            'subject' => 'Email di prova da :app',
            'body' => 'Questa email è stata inviata dalla configurazione di :app: le email dell\'app arrivano ai destinatari.',
            'sent' => 'Email di prova inviata a :email.',
            'sent_body' => 'Se non arriva entro pochi minuti, controlla la cartella dello spam e le impostazioni email del server (MAIL_*).',
            'failed' => 'Non è stato possibile inviare l\'email di prova.',
        ],
    ],

    'administrators' => [
        'intro' => 'Aggiungi le persone che gestiranno l\'app con te: diventano super amministratori e ricevono un\'email per scegliere la password. Tu (:email) lo sei già.',
        'add' => 'Aggiungi un amministratore',
    ],

    'features' => [
        'sharing' => 'Condivisione delle assegnazioni',
        'sharing_description' => 'Se le assegnazioni si possono condividere con un link pubblico, con chi non ha un account.',
    ],

    'skip' => [
        'label' => 'Salta',
        'heading' => 'Saltare la configurazione?',
        'description' => 'La procedura non verrà più proposta: tutto ciò che imposta si può cambiare in seguito dalle impostazioni e dagli utenti.',
        'confirm' => 'Salta la configurazione',
    ],

    'completed' => [
        'title' => 'L\'app è configurata',
        'body' => '{0} Puoi cambiare queste scelte in seguito dalle impostazioni.|{1} :count amministratore è stato invitato via email.|[2,*] :count amministratori sono stati invitati via email.',
    ],

    'banner' => [
        'text' => 'L\'app non è ancora configurata: scegli il suo nome, gli amministratori e le funzionalità da attivare.',
        'action' => 'Configura l\'app',
    ],
];
