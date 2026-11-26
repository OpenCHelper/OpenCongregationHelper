<?php

return [
    'title' => 'Installa l\'app',
    'subheading' => 'Pochi passaggi per preparare l\'app per la congregazione.',
    'changeable_later' => 'Tutto ciò che scegli dopo l\'utente root si può cambiare in seguito dalle impostazioni.',
    'complete' => 'Installa',

    'token' => [
        'missing_heading' => 'Aggiungi il token di configurazione',
        'missing_description' => 'L\'app non è ancora installata. Perché nessun altro possa installarla prima di te, aggiungi un token di configurazione a tua scelta nel file .env sul server, es. :example, poi ricarica questa pagina.',
        'description' => 'Scrivi il valore di SETUP_TOKEN, dal file .env sul server.',
        'label' => 'Token di configurazione',
        'submit' => 'Continua',
        'invalid' => 'Il token di configurazione non è corretto.',
        'throttled' => 'Troppi tentativi: riprova tra :seconds secondi.',
    ],

    'steps' => [
        'counter' => 'Passaggio :step di :total',
        'requirements' => [
            'label' => 'Server',
            'description' => 'Requisiti',
        ],
        'environment' => [
            'label' => 'Configurazione',
            'description' => 'Il file .env',
        ],
        'tasks' => [
            'label' => 'Attività in background',
            'description' => 'Scheduler e coda',
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
        'review' => [
            'label' => 'Riepilogo',
            'description' => 'Controlla e installa',
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
        'password_keep' => 'Impostata nel file .env: lascia vuoto per mantenerla',
        'default_option' => 'Predefinito: :value',
        'intro' => 'Questi valori mancano nel file .env: verranno aggiunti. I valori già impostati non vengono mai cambiati.',
        'nothing_missing' => 'Tutti i valori che servono all\'app sono già impostati nel file .env.',
        'not_writable' => 'Il file .env non può essere scritto: aggiungi tu i valori mancanti, poi ricarica questa pagina.',
        'application' => 'Applicazione',
        'app_url' => 'L\'indirizzo pubblico dell\'app, usato nei link delle email.',
        'app_timezone' => 'Usato per le date e per l\'orario dei promemoria.',
        'app_locale' => 'Lingua dell\'app per gli utenti che non ne hanno scelta una.',
        'database' => 'Database',
        'db_prefix' => 'Prefisso dei nomi delle tabelle, per condividere il database con altre applicazioni (facoltativo).',
        'db_sqlite' => 'Il percorso completo del file del database: viene creato se non esiste.',
        'mail' => 'Email',
        'mailers' => [
            'log' => 'Log: non inviare, scrivi solo nel log',
            'array' => 'Array: non inviare, tieni solo in memoria (test)',
            'failover' => 'Failover: prova a turno i mailer della configurazione',
            'roundrobin' => 'Round robin: distribuisci le email tra i mailer della configurazione',
            'nativemailfx' => 'Funzione mail() di PHP',
        ],
        'keys' => 'Chiavi',
        'push_keys' => 'Notifiche push (VAPID_*)',
        'passport_keys' => 'Assistenti IA',
        'push_not_writable' => 'Il file .env non può essere scritto: creale con php artisan webpush:vapid.',
        'key_statuses' => [
            'set' => 'Già impostate',
            'created' => 'Create proseguendo',
            'unavailable' => 'Non possono essere create',
        ],
        'keys_failed' => 'Non è stato possibile creare le chiavi (`:command`).',
        'mail_from_address' => 'Il mittente delle email dell\'app.',
        'database_failed' => 'L\'app non riesce a collegarsi al database.',
        'write_failed' => 'Non è stato possibile scrivere il file .env.',
    ],

    'tasks' => [
        'intro' => 'Una parte del lavoro avviene in background: le attività pianificate (es. i promemoria settimanali) e, con una coda, l\'invio delle email. Configuralo ora o dopo l\'installazione: l\'app funziona lo stesso, ma queste attività non vengono eseguite.',
        'queue_helper' => 'Dove le email e gli altri lavori in background attendono di essere elaborati.',
        'with_shell' => 'Con una shell',
        'without_shell' => 'Senza una shell',
        'cron_intro' => 'Aggiungi questa riga al crontab del server (crontab -e), per eseguire lo scheduler ogni minuto:',
        'worker_intro' => 'E questa per elaborare la coda (oppure esegui php artisan queue:work come processo continuo, es. con Supervisor):',
        'sync_note' => 'Con questa coda nulla resta in attesa: il lavoro viene svolto subito e non serve un worker.',
        'service_intro' => 'Su un hosting senza shell, fai chiamare questi indirizzi ogni minuto da un servizio cron esterno, con il metodo POST:',
        'token_intro' => 'Ogni chiamata deve inviare questo header, che dimostra che arriva dal tuo servizio:',
        'token_disabled' => 'Questi indirizzi sono disattivati finché non hanno un segreto (ARTISAN_CRON_TOKEN): attivali solo se li usi.',
        'enable_endpoints' => 'Attiva gli indirizzi',
        'copy' => 'Copia',
        'queues' => [
            'sync' => 'Sync: subito, durante la richiesta (non serve un worker)',
            'database' => 'Database: nel database, elaborata da un worker (consigliata)',
        ],
    ],
    'database' => [
        'intro' => 'Proseguendo vengono create le tabelle del database e i permessi dei ruoli. Può richiedere un minuto.',
        'running' => 'Creazione delle tabelle… Può richiedere un minuto: non chiudere la pagina.',
        'failed' => 'Non è stato possibile preparare il database (`:command`).',
    ],

    'root' => [
        'intro' => 'L\'utente root può fare tutto e non può mai essere eliminato: è il tuo account. Invia un\'email di prova per verificare che le email dell\'app arrivino.',
        'password' => 'Password',
        'password_hint' => 'Almeno :min caratteri.',
        'password_hint_uncompromised' => 'Non deve essere tra le password trapelate online.',
        'generate_password' => 'Genera una password',
        'password_generated' => 'Password generata',
        'password_generated_body' => 'Salvala ora, es. in un gestore di password: :password',
        'password_confirmation' => 'Conferma la password',
        'email_first' => 'Scrivi prima un indirizzo email valido.',
        'test_email_hint' => 'Nessuna email di prova inviata: per richiedere la conferma dell\'email ne serve una.',
        'mailer_not_sending' => 'Le email non vengono inviate (MAIL_MAILER è :mailer): l\'email di prova non arriva in nessuna casella. Per riceverla scegli un mailer che le invia nel passaggio Configurazione.',
        'go_to_configuration' => 'Vai al passaggio Configurazione',
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

    'review' => [
        'intro' => 'Controlla le tue scelte, poi installa. Torna a un passaggio qualsiasi per cambiarle.',
        'edit' => 'Modifica',
        'test_email' => 'Email di prova',
        'test_email_sent' => 'Inviata a :email',
        'test_email_not_sent' => 'Non inviata',
        'none' => 'Nessuno',
        'enabled' => 'Sì',
        'disabled' => 'No',
    ],

    'completed' => [
        'heading' => 'L\'app è installata',
        'description' => 'Accedi come utente root per iniziare a usare l\'app.',
        'items' => [
            'database' => 'Tabelle e permessi creati',
            'root' => 'Utente root creato: :email',
            'settings' => 'Impostazioni salvate',
            'administrators' => '{0} Nessun amministratore aggiunto: puoi aggiungerli in seguito dagli utenti|{1} :count amministratore invitato via email|[2,*] :count amministratori invitati via email',
            'invitations_failed' => '{1} :count invito non è stato inviato: invialo di nuovo dagli utenti|[2,*] :count inviti non sono stati inviati: inviali di nuovo dagli utenti',
            'two_factor' => 'Configura l\'autenticazione a due fattori subito dopo l\'accesso',
            'token_removed' => 'SETUP_TOKEN rimosso dal file .env',
            'remove_token' => 'Rimuovi SETUP_TOKEN dal file .env: non serve più',
        ],
        'log_in' => 'Accedi',
    ],
];
