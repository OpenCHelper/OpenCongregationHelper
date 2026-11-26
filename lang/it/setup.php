<?php

return [
    'title' => 'Installa l\'app',
    'subheading' => 'Pochi passaggi per preparare l\'app per la congregazione. Tutto ciò che scegli dopo l\'utente root si può cambiare in seguito dalle impostazioni.',
    'complete' => 'Installa',

    'token' => [
        'missing_heading' => 'Aggiungi il token di configurazione',
        'missing_description' => 'L\'app non è ancora installata. Perché nessun altro possa installarla prima di te, aggiungi un token di configurazione a tua scelta nel file .env sul server, es. :example, poi ricarica questa pagina.',
        'description' => 'Scrivi il token di configurazione, il valore di SETUP_TOKEN nel file .env sul server.',
        'label' => 'Token di configurazione',
        'submit' => 'Continua',
        'invalid' => 'Il token di configurazione non è corretto.',
        'throttled' => 'Troppi tentativi: riprova tra :seconds secondi.',
    ],

    'steps' => [
        'requirements' => [
            'label' => 'Server',
            'description' => 'Requisiti',
        ],
        'environment' => [
            'label' => 'Configurazione',
            'description' => 'Il file .env',
        ],
        'database' => [
            'label' => 'Database',
            'description' => 'Crea le tabelle',
        ],
        'root' => [
            'label' => 'Utente root',
            'description' => 'Il tuo account',
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
        'all_ok' => 'Il server soddisfa i requisiti.',
        'problems' => '{1} :count controllo richiede attenzione.|[2,*] :count controlli richiedono attenzione.',
        'errors' => 'Correggi gli errori prima di proseguire, poi controlla di nuovo.',
        'check_again' => 'Controlla di nuovo',
        'test_email' => [
            'label' => 'Invia un\'email di prova',
            'subject' => 'Email di prova da :app',
            'body' => 'Questa email è stata inviata dall\'installazione di :app: le email dell\'app arrivano ai destinatari.',
            'sent' => 'Email di prova inviata a :email.',
            'sent_body' => 'Se non arriva entro pochi minuti, controlla la cartella dello spam e le impostazioni email (MAIL_*).',
            'failed' => 'Non è stato possibile inviare l\'email di prova.',
        ],
    ],

    'environment' => [
        'already_set' => 'Già impostato nel file .env',
        'password_set' => 'Impostata nel file .env',
        'intro' => 'Questi valori mancano nel file .env: verranno aggiunti. I valori già impostati non vengono mai cambiati.',
        'nothing_missing' => 'Tutti i valori che servono all\'app sono già impostati nel file .env.',
        'not_writable' => 'Il file .env non può essere scritto: aggiungi tu i valori mancanti, poi ricarica questa pagina.',
        'application' => 'Applicazione',
        'app_url' => 'L\'indirizzo pubblico dell\'app, usato nei link delle email.',
        'app_timezone' => 'Usato per le date e per l\'orario dei promemoria.',
        'app_locale' => 'Lingua dell\'app per gli utenti che non ne hanno scelta una.',
        'database' => 'Database',
        'db_sqlite' => 'Il percorso completo del file del database: viene creato se non esiste.',
        'mail' => 'Email',
        'mailers' => [
            'log' => 'Log: non inviare, scrivi solo nel log',
            'array' => 'Array: non inviare, tieni solo in memoria (test)',
            'failover' => 'Failover: prova a turno i mailer della configurazione',
            'roundrobin' => 'Round robin: distribuisci le email tra i mailer della configurazione',
            'nativemailfx' => 'Funzione mail() di PHP',
        ],
        'mail_from_address' => 'Il mittente delle email dell\'app.',
        'database_failed' => 'L\'app non riesce a collegarsi al database.',
        'write_failed' => 'Non è stato possibile scrivere il file .env.',
    ],

    'database' => [
        'intro' => 'Proseguendo vengono create le tabelle del database, i permessi dei ruoli e le chiavi degli assistenti IA. Può richiedere un minuto.',
        'failed' => 'Non è stato possibile preparare il database (`:command`).',
    ],

    'root' => [
        'intro' => 'L\'utente root può fare tutto e non può mai essere eliminato: è il tuo account. Invia un\'email di prova per verificare che le email dell\'app arrivino.',
        'password' => 'Password',
        'password_confirmation' => 'Conferma la password',
        'email_first' => 'Scrivi prima un indirizzo email valido.',
        'test_email_hint' => 'Nessuna email di prova inviata: per richiedere la conferma dell\'email ne serve una.',
        'mailer_not_sending' => 'Le email non vengono inviate (MAIL_MAILER è :mailer): l\'email di prova non arriva in nessuna casella. Per riceverla scegli un mailer che le invia nel passaggio Configurazione.',
    ],

    'administrators' => [
        'intro' => 'Aggiungi le persone che gestiranno l\'app con te: diventano super amministratori e ricevono un\'email per scegliere la password. Puoi aggiungerle anche in seguito.',
        'add' => 'Aggiungi un amministratore',
        'root_email' => 'Questo è l\'indirizzo email dell\'utente root.',
    ],

    'features' => [
        'sharing' => 'Condivisione delle assegnazioni',
        'sharing_description' => 'Se le assegnazioni si possono condividere con un link pubblico, con chi non ha un account.',
        'two_factor_root' => 'La configurerai subito dopo l\'accesso.',
        'email_verification_needs_test' => 'Invia prima un\'email di prova, dal passaggio Utente root: senza email funzionanti nessuno potrebbe confermare il proprio indirizzo.',
    ],

    'completed' => [
        'heading' => 'L\'app è installata',
        'description' => 'Accedi come utente root. Il token di configurazione non serve più: puoi rimuovere SETUP_TOKEN dal file .env.',
        'log_in' => 'Accedi',
    ],
];
