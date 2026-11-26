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
APP_URL=https://och-instance.example.com

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
INITIAL_ROOT_PASSWORD="una-frase-lunga-e-unica"
```

### Impostazioni principali

`MAP_DEFAULT_CENTER`, `ENABLE_SHARED_PII`, `GUEST_ALLOW_RETURNING_ASSIGNMENT` e `PASSWORD_LEAK_CHECK` sono solo i
valori predefiniti: i super amministratori possono cambiarli dalle pagine [Impostazioni](administration/settings),
senza modificare `.env`. Se la cache delle impostazioni è attiva (`SETTINGS_CACHE_ENABLED=true`), dopo averli cambiati in `.env` esegui
`php artisan settings:clear-cache`, altrimenti le impostazioni non cambiate da quelle pagine mantengono il valore
precedente.

| Variabile | Descrizione |
|-----------|-------------|
| `APP_URL` | Indirizzo pubblico dell'applicazione. Usato nelle email e nei link delle assegnazioni condivise. |
| `APP_LOCALE` | Lingua predefinita (`en`, `it` o `es`). Ogni utente può poi scegliere la propria lingua. |
| `APP_TIMEZONE` | Fuso orario usato per mostrare le date, e in cui si contano il giorno e l'ora dei promemoria settimanali (vedi le pagine [Impostazioni](administration/settings)) e l'ora del rinnovo giornaliero delle pubblicazioni (alle 9:00). |
| `DB_PREFIX` / `DB_ENGINE` | Prefisso delle tabelle e motore di archiviazione, facoltativi, utili sui database condivisi: `db:wipe` e `migrate:fresh` eliminano allora solo le tabelle con questo prefisso. |
| `TRUSTED_PROXIES` | Indirizzi (o intervalli CIDR, separati da virgole) dei reverse proxy davanti all'applicazione, di cui ci si fida per le intestazioni `X-Forwarded-For` e `X-Forwarded-Proto`. Predefinito: `*` (qualsiasi peer, necessario quando l'indirizzo del proxy non è noto, es. su Altervista); lasciala vuota quando l'applicazione è raggiunta direttamente, senza proxy. |
| `INITIAL_ROOT_USERNAME`, `INITIAL_ROOT_USERMAIL`, `INITIAL_ROOT_PASSWORD` | Nome (nome e cognome separati da uno spazio), email e password dell'utente **root**, creato da `och:install` quando il database non ha ancora utenti. La password segue le stesse regole delle password scelte dagli utenti (vedi `PASSWORD_LEAK_CHECK`): `och:install` e `och:recover-root` rifiutano una password che non le rispetta, oppure ne chiedono un'altra se eseguiti da terminale. |
| `PASSWORD_LEAK_CHECK` | Quando `APP_ENV` è `production`, le password devono avere almeno 12 caratteri (8 altrove). Se è `true`, non devono nemmeno comparire in fughe di dati note: vengono verificate con [Have I Been Pwned](https://haveibeenpwned.com/Passwords), inviando ad `api.pwnedpasswords.com` solo i primi 5 caratteri del loro hash SHA-1. Se il servizio non risponde entro 5 secondi la password viene accettata. Predefinito: `true` in produzione (impostala a `false` sui server che non possono raggiungere il servizio) e `false` altrove. I super amministratori possono cambiarla dalle [impostazioni di sicurezza](administration/settings#sicurezza). |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Come vengono inviate le email. `failover` prova prima la funzione nativa `mail()` di PHP e in caso di errore scrive l'email nel log. |
| `QUEUE_CONNECTION` | `database` (consigliato in produzione) o `sync` (le email vengono inviate subito, durante la richiesta). |
| `SETUP_TOKEN` | Apre la [configurazione guidata](#dal-browser) finché l'applicazione non è installata. Scegli un valore casuale e lungo, e rimuovilo una volta installata. |
| `SETUP_WIZARD` | `false` disattiva la configurazione guidata, es. per le installazioni fatte dal terminale. Predefinito: `true`. |
| `ARTISAN_CRON_TOKEN` | Segreto richiesto dagli endpoint che elaborano la coda e le attività pianificate. Se vuoto, gli endpoint sono disabilitati. |
| `ENABLE_SHARED_PII` | Se `true`, la pagina pubblica di un'assegnazione condivisa mostra anche il nome dell'assegnatario e le note del territorio. Predefinito: `false`. |
| `GUEST_ALLOW_RETURNING_ASSIGNMENT` | Se `true`, chi apre la pagina pubblica di un'assegnazione condivisa può chiedere al reparto territori di chiuderla (come può fare l'assegnatario dall'applicazione). Predefinito: `false`. |
| `DEMO_PREFILL_LOGIN_CREDENTIALS` | **Solo per installazioni dimostrative**, con dati fittizi: se `true`, la pagina di accesso è precompilata con le credenziali dell'utente **root**, quindi chiunque può accedere come root. Non attivarla mai su un'installazione con dati reali. Sempre ignorata quando `APP_ENV` è `production`. Predefinito: `false`. |
| `APP_VERSION_BRANCH_FILE`, `APP_VERSION_COMMIT_FILE` | File da cui vengono letti il branch e il commit Git (di default `.git/HEAD` e `.git/refs/heads/<branch>`), mostrati in **Informazioni** nel menu utente fuori da `production`. Utili sui server senza la cartella `.git`. |

## Installazione

### Dal browser

Una volta caricati i file sul server (con le dipendenze PHP e gli asset del frontend), aggiungi un `SETUP_TOKEN` a tua
scelta nel file `.env`, poi apri l'applicazione: finché non è installata, ogni pagina apre la **configurazione
guidata** (`/setup`), che chiede il token e poi:

1. controlla il server: versione ed estensioni di PHP, HTTPS e il suo certificato, cartelle e file `.env` scrivibili;
2. chiede i valori che mancano nel file `.env` (indirizzo, fuso orario, lingua, database ed email) e li aggiunge al
   file, dopo aver verificato che il database sia raggiungibile. I valori già impostati vengono mostrati ma mai
   cambiati (le password solo come impostate), tranne il mailer (`MAIL_MAILER`), che si può cambiare tra quelli di
   `config/mail.php`, così che l'email di prova possa essere inviata in un altro modo;
3. crea le tabelle del database, i permessi e le chiavi degli [assistenti IA](#assistenti-ia);
4. crea l'utente root, e può inviargli un'email di prova;
5. imposta il nome, il logo e il colore dell'app, i suoi amministratori (invitati via email) e le sue funzionalità: gli
   assistenti IA, la condivisione delle assegnazioni e i requisiti degli account. Per richiedere la conferma dell'email
   serve prima un'email di prova andata a buon fine; richiedendo l'autenticazione a due fattori, l'utente root la
   configura subito dopo l'accesso.

Poi accedi come utente root e rimuovi `SETUP_TOKEN` dal file `.env`. Senza un token, la configurazione guidata spiega
solo come aggiungerne uno, così che nessun altro possa installare l'applicazione prima di te.

### Dal terminale

1. Installa le dipendenze PHP e compila gli asset del frontend:

    ```shell
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    ```

2. Crea il file `.env` (vedi sopra) e genera la chiave dell'applicazione:

    ```shell
    php artisan key:generate
    ```

3. Crea le tabelle del database, i permessi, le chiavi degli [assistenti IA](#assistenti-ia) e l'utente root con il
   ruolo `super_admin`, poi il collegamento `public/storage` e le cache:

    ```shell
    php artisan och:post-deploy
    ```

    L'utente root viene letto dalle variabili `INITIAL_ROOT_*`, e quelle mancanti vengono chieste.

4. Controlla che non manchi nulla, poi apri `/app/login` e accedi con le credenziali `INITIAL_ROOT_*`. Poi segui la
   guida [Configurare la congregazione](guides/first-setup).

    ```shell
    php artisan och:doctor
    ```

> 🔒 **L'utente root** è l'utente con ID 1. Non può mai essere eliminato né impersonato e, al di fuori dell'ambiente
> `production`, nessuno può modificarne email, password, accesso e ruoli.

## Aggiornamento

Sostituisci i file con quelli della nuova versione (mantenendo `.env` e `storage/`), poi esegui:

```shell
php artisan och:post-deploy
```

Esegue le nuove migrazioni, crea ciò che ancora manca (`och:install`), ricrea il collegamento `public/storage` e
ricostruisce le cache, fermandosi al primo passaggio che non riesce. Con `--no-cache` le cache vengono solo svuotate,
senza ricostruirle (es. su una macchina di sviluppo, dove nasconderebbero le modifiche al codice).

**Senza accesso alla shell**, i comandi vengono eseguiti tramite l'endpoint `/api/artisan/execute`, autorizzato da un
token monouso caricato via FTP: `scripts/remote_artisan.sh` fa entrambe le cose. Per esempio:

```shell
scripts/remote_artisan.sh --base-url https://och-instance.example.com \
    --ftp-hostname <host> --ftp-username <utente> --ftp-password <password> \
    och:post-deploy --no-interaction
```

## Risoluzione dei problemi

- **`php artisan och:doctor`** controlla la configurazione (chiave e URL dell'applicazione, HTTPS, database e
  migrazioni, collegamento dello storage, chiavi degli assistenti IA, utente root e la sua autenticazione a due
  fattori, super amministratori, email, notifiche push, endpoint per il cron) e indica cosa fare per ogni problema. Non
  modifica nulla, quindi può essere eseguito in qualsiasi momento. La [configurazione guidata](#dal-browser) controlla il server allo stesso modo.
- **`php artisan och:recover-root`** restituisce l'accesso all'utente root quando nessuno può più gestire
  l'applicazione (es. password persa, oppure accesso o ruoli rimossi in produzione): imposta la sua password da
  `INITIAL_ROOT_PASSWORD` (o la chiede), gli permette di nuovo di accedere, segna la sua email come verificata,
  disattiva la sua autenticazione a due fattori e lo rende super amministratore. Chiede una conferma, oppure richiede
  `--force` quando viene eseguito senza terminale (es. tramite `scripts/remote_artisan.sh`), e viene sempre registrato
  nel log.

## Attività pianificate ed email

Le email di notifica vengono messe in coda, un'attività settimanale invia i promemoria per i territori fuori da molto
tempo e una giornaliera rinnova le pubblicazioni periodiche. Richiedono qualcosa che venga eseguito periodicamente:

- **Con accesso alla shell**, avvia il worker della coda (`php artisan queue:work`) e lo scheduler di Laravel, dal
  crontab (`* * * * * php /percorso/di/artisan schedule:run`) o come processo sempre attivo
  (`php artisan schedule:work`, es. con Supervisor). Riavvia il worker della coda dopo aver installato una nuova
  versione.
- **Senza accesso alla shell** (hosting condiviso), imposta `ARTISAN_CRON_TOKEN` e configura un servizio di cron esterno
  che chiami questi endpoint, raggiungibili sotto il percorso `/api/artisan` di `APP_URL`:

    ```shell
    # Elabora la coda (es. ogni 5 minuti)
    curl -X POST -H "X-Cron-Token: <il tuo token>" https://och-instance.example.com/api/artisan/queue/process

    # Esegue le attività pianificate (es. ogni ora)
    curl -X POST -H "X-Cron-Token: <il tuo token>" https://och-instance.example.com/api/artisan/schedule/process
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

## Assistenti IA

Gli utenti possono collegare all'applicazione degli assistenti IA (es. ChatGPT, Claude, Gemini) tramite il suo server
MCP (`/mcp`), una volta che un super amministratore li consente nelle
[impostazioni di sicurezza](administration/settings#sicurezza). Gli assistenti si autorizzano con OAuth, i cui token
sono firmati da due chiavi create una volta da `och:install` (eseguito anche da `och:post-deploy`), oppure da sole con:

```shell
php artisan och:ensure-passport-keys
```

Il comando crea `storage/oauth-private.key` e `storage/oauth-public.key` solo se mancano, quindi può essere eseguito a
ogni installazione: conservale tra un'installazione e l'altra, perché delle nuove chiavi scollegano tutti gli
assistenti. Come il file `.env`, non devono mai essere raggiungibili dal web (il file `.htaccess` del progetto espone
solo la cartella `public/`).

Gli assistenti di ChatGPT, Claude e Gemini si collegano dai propri server: l'applicazione deve essere raggiungibile da
internet in HTTPS, con un certificato valido.

## Ambienti

I super amministratori vedono un bordo colorato e un'etichetta con il nome dell'ambiente (`APP_ENV`): rossi in
`production`, di altri colori altrove (es. `local`, `nightly`, `staging`), così da non confondere un'installazione di
prova con quella reale.
