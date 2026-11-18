---
type: documentation
icon: heroicon-o-wrench-screwdriver
title: Guida al setup
order: 0
---

Questa pagina è rivolta a chi **installa e gestisce** l'applicazione. Se devi solo usarla, parti dalla sezione
**Nozioni di base**.

## Requisiti

- PHP 8.2 o maggiore, con le estensioni `pdo_mysql` (o `pdo_sqlite`), `gd`, `intl` e `zip`;
- un database MySQL/MariaDB (funziona anche SQLite, utile soprattutto in sviluppo);
- Node.js, solo per compilare gli asset del frontend (`npm run build`);
- un modo per inviare email (la funzione nativa `mail()` di PHP è supportata senza configurazioni aggiuntive).

L'applicazione funziona anche su hosting condivisi senza accesso alla shell (es. Altervista): vedi
[Attività pianificate ed email](#attività-pianificate-ed-email).

## Configurazione

L'applicazione è configurata usando un file `.env`.

> ⚠️ **Warning** ⚠️
>
> Non sottomettere mai il file `.env` in un sistema di versionamento (es. `git`).

Una configurazione minimale potrebbe essere la seguente:

```shell
APP_NAME="My Application Name"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://territori.example.com

# Imposta qui la tua locale
APP_LOCALE=it

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=my_database_name
DB_USERNAME=my_database_username
DB_PASSWORD=my_database_password
DB_ENGINE=InnoDB

# Questo sarà l'utente root
INITIAL_ROOT_USERNAME="Root User"
INITIAL_ROOT_USERMAIL="root@example.com"
INITIAL_ROOT_PASSWORD="password"
```

### Impostazioni principali

`APP_NAME`, `APP_LOCALE`, `APP_TIMEZONE`, `MAP_DEFAULT_CENTER`, `ENABLE_SHARED_PII` e `MAIL_FROM_NAME` sono solo i
valori predefiniti: i super amministratori possono cambiarli dalle pagine [Impostazioni](administration/settings), senza
modificare `.env`.

| Variabile | Descrizione |
|-----------|-------------|
| `APP_URL` | Indirizzo pubblico dell'applicazione. Usato nelle email e nei link delle assegnazioni condivise. |
| `APP_LOCALE` | Lingua predefinita (`en`, `it` o `es`). Ogni utente può poi scegliere la propria lingua. |
| `APP_TIMEZONE` | Fuso orario usato per mostrare le date. |
| `DB_PREFIX` / `DB_ENGINE` | Prefisso delle tabelle e motore di archiviazione, facoltativi, utili sui database condivisi: `db:wipe` e `migrate:fresh` eliminano allora solo le tabelle con questo prefisso. |
| `INITIAL_ROOT_USERNAME`, `INITIAL_ROOT_USERMAIL`, `INITIAL_ROOT_PASSWORD` | Nome (nome e cognome separati da uno spazio), email e password dell'utente **root**, creato la prima volta che il database viene popolato. |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Come vengono inviate le email. `failover` prova prima la funzione nativa `mail()` di PHP e in caso di errore scrive l'email nel log. |
| `QUEUE_CONNECTION` | `database` (consigliato in produzione) o `sync` (le email vengono inviate subito, durante la richiesta). |
| `ARTISAN_CRON_TOKEN` | Segreto richiesto dagli endpoint che elaborano la coda e le attività pianificate. Se vuoto, gli endpoint sono disabilitati. |
| `ENABLE_SHARED_PII` | Se `true`, la pagina pubblica di un'assegnazione condivisa mostra anche il nome dell'assegnatario e le note del territorio. Predefinito: `false`. |
| `DEMO_PREFILL_LOGIN_CREDENTIALS` | **Solo per installazioni dimostrative**, con dati fittizi: se `true`, la pagina di accesso è precompilata con le credenziali dell'utente **root**, quindi chiunque può accedere come root. Non attivarla mai su un'installazione con dati reali. Sempre ignorata quando `APP_ENV` è `production`. Predefinito: `false`. |
| `APP_VERSION_BRANCH_FILE`, `APP_VERSION_COMMIT_FILE` | File usati per mostrare il branch e il commit Git nella barra laterale sui server senza `git`. |

## Installazione

1. Installa le dipendenze PHP e compila gli asset del frontend:

    ```shell
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    ```

2. Crea il file `.env` (vedi sopra) e genera la chiave dell'applicazione:

    ```shell
    php artisan key:generate
    ```

3. Crea le tabelle del database e l'utente root:

    ```shell
    php artisan migrate --force
    php artisan db:seed --force
    ```

4. Crea i permessi e assegna all'utente root il ruolo `super_admin`:

    ```shell
    php artisan db:seed --class=ShieldSeeder --force
    php artisan shield:super-admin --user=1 --panel=app
    ```

5. Apri `/app/login` e accedi con le credenziali `INITIAL_ROOT_*`. Poi segui la guida
   [Configurare la congregazione](guides/first-setup).

> 🔒 **L'utente root** è l'utente con ID 1. Non può mai essere eliminato né impersonato e, al di fuori dell'ambiente
> `production`, nessuno può modificarne email, password, accesso e ruoli.

## Attività pianificate ed email

Le email di notifica vengono messe in coda e un'attività settimanale invia i promemoria per i territori fuori da molto
tempo. Entrambe richiedono qualcosa che venga eseguito periodicamente:

- **Con accesso alla shell**, avvia il worker della coda (`php artisan queue:work`) e lo scheduler di Laravel, dal
  crontab (`* * * * * php /percorso/di/artisan schedule:run`) o come processo sempre attivo
  (`php artisan schedule:work`, es. con Supervisor). Riavvia il worker della coda dopo aver installato una nuova
  versione.
- **Senza accesso alla shell** (hosting condiviso), imposta `ARTISAN_CRON_TOKEN` e configura un servizio di cron esterno
  che chiami questi endpoint, raggiungibili sotto il percorso `/api/artisan` di `APP_URL`:

    ```shell
    # Elabora la coda (es. ogni 5 minuti)
    curl -X POST -H "X-Cron-Token: <il tuo token>" https://territori.example.com/api/artisan/queue/process

    # Esegue le attività pianificate (es. ogni ora)
    curl -X POST -H "X-Cron-Token: <il tuo token>" https://territori.example.com/api/artisan/schedule/process
    ```

  Gli endpoint hanno un limite di 10 richieste al minuto.

## Notifiche push

Le notifiche push sono facoltative: finché non sono configurate, le notifiche vengono inviate solo via email e
all'interno dell'applicazione, e il profilo non propone di attivarle. Per abilitarle, genera le chiavi una sola volta:

```shell
php artisan webpush:vapid
```

Il comando scrive `VAPID_PUBLIC_KEY` e `VAPID_PRIVATE_KEY` nel file `.env`. Non cambiarle in seguito, altrimenti ogni
utente dovrà riattivare le notifiche push. Facoltativamente, imposta `VAPID_SUBJECT` con un contatto dell'amministratore
(es. `mailto:admin@example.com`), fornito ai servizi push dei browser (`APP_URL` se non impostato).

Le notifiche push richiedono HTTPS e, come le email, vengono inviate dalla coda (vedi sopra).

> ⚠️ **In produzione** genera le chiavi una sola volta, a mano, e conservane una copia insieme agli altri segreti
> dell'installazione: non farle mai generare a una pipeline di deploy. Solo gli ambienti di test il cui database viene
> azzerato a ogni deploy (Nightly e le anteprime delle PR) ricevono nuove chiavi ogni volta, dato che le loro
> iscrizioni vengono comunque eliminate.

Gli utenti che non possono accedere non ricevono notifiche push, e i loro dispositivi vengono dimenticati quando
l'accesso viene revocato.

## Ambienti

Negli ambienti diversi da `production` (es. `local`, `nightly`, `staging`) i super amministratori vedono un bordo
colorato e un'etichetta con il nome dell'ambiente, così da non confondere un'installazione di prova con quella reale.
