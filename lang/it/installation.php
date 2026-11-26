<?php

return [
    'statuses' => [
        'ok' => 'OK',
        'warning' => 'Avviso',
        'error' => 'Errore',
    ],

    'checks' => [
        'php_version' => [
            'label' => 'Versione di PHP',
            'failed' => 'PHP :version è troppo vecchio: l\'app richiede PHP :minimum o successivo',
        ],
        'php_extensions' => [
            'label' => 'Estensioni di PHP',
            'failed' => 'mancanti: :extensions',
        ],
        'app_key' => [
            'label' => 'Chiave dell\'applicazione (APP_KEY)',
            'failed' => 'esegui `php artisan key:generate`',
        ],
        'app_url' => [
            'label' => 'URL dell\'applicazione (APP_URL)',
            'failed' => 'impostalo all\'indirizzo pubblico: lo usano i link nelle email',
        ],
        'https' => [
            'label' => 'Connessione sicura (HTTPS)',
            'failed' => 'APP_URL non inizia con https://: i browser possono installare l\'app e ricevere le notifiche push solo in HTTPS',
            'invalid' => 'il certificato non è valido, i browser mostreranno un avviso: :error',
            'expiring' => 'il certificato scade il :date: rinnovalo',
            'valid' => 'certificato valido fino al :date',
        ],
        'debug' => [
            'label' => 'Modalità debug (APP_DEBUG)',
            'failed' => 'disattivala in produzione: gli errori mostrerebbero dettagli interni',
        ],
        'timezone' => [
            'label' => 'Fuso orario (APP_TIMEZONE)',
        ],
        'cron_token' => [
            'label' => 'Endpoint della coda e dello scheduler (ARTISAN_CRON_TOKEN)',
            'enabled' => 'attivi',
            'disabled' => 'disattivati (servono solo senza una shell)',
        ],
        'mail' => [
            'label' => 'Email (MAIL_MAILER, MAIL_FROM_ADDRESS)',
            'failed' => 'le email di notifica non possono essere inviate',
        ],
        'push' => [
            'label' => 'Notifiche push (VAPID_*)',
            'failed' => 'le notifiche push sono disattivate: esegui `php artisan webpush:vapid`',
        ],
        'initial_root' => [
            'label' => 'Utente root iniziale (INITIAL_ROOT_*)',
            'failed' => 'serve a `och:install` su una nuova installazione, e a `och:recover-root`',
        ],
        'database' => [
            'label' => 'Connessione al database',
        ],
        'migrations' => [
            'label' => 'Migrazioni',
            'empty' => 'il database è vuoto: esegui `php artisan och:post-deploy`',
            'failed' => ':count in sospeso: esegui `php artisan och:post-deploy`',
        ],
        'storage_link' => [
            'label' => 'Collegamento dello storage pubblico (public/storage)',
            'failed' => 'mancante: i file caricati non possono essere mostrati, esegui `php artisan storage:link`',
        ],
        'passport_keys' => [
            'label' => 'Chiavi degli assistenti IA',
            'failed' => 'mancanti: gli assistenti IA non possono collegarsi, esegui `php artisan och:install`',
        ],
        'writable_storage' => [
            'label' => 'Storage scrivibile',
            'failed' => 'storage/framework e storage/logs devono essere scrivibili',
        ],
        'env_file' => [
            'label' => 'File .env scrivibile',
            'failed' => 'la configurazione guidata non può scrivere i valori mancanti: impostali tu nel file .env',
        ],
        'root_user' => [
            'label' => 'Utente root (ID 1)',
            'failed' => 'mancante: esegui `php artisan och:install`',
        ],
        'root_can_login' => [
            'label' => 'L\'utente root può accedere',
            'failed' => 'accesso disattivato: esegui `php artisan och:recover-root` se nessun altro può gestire gli utenti',
        ],
        'root_two_factor' => [
            'label' => 'Autenticazione a due fattori dell\'utente root',
            'failed' => 'consigliata: l\'utente root può fare tutto, attivala dal suo profilo',
        ],
        'super_admins' => [
            'label' => 'Super amministratori',
            'failed' => 'nessuno può gestire l\'applicazione: esegui `php artisan och:install`',
        ],
    ],
];
