---
type: documentation
icon: heroicon-o-arrow-path
title: Backup e aggiornamento
order: 0
---

Questa pagina è rivolta a chi **gestisce** un'installazione dell'applicazione: come farne il backup, ripristinarla e
aggiornarla a una nuova versione. Per installarla la prima volta, vedi la [Guida al setup](setup).

## Cosa salvare

Un'installazione è fatta del suo codice, che si può sempre scaricare di nuovo, e dei suoi dati, che esistono solo sul
tuo server:

| Cosa | Dove | Cosa contiene |
|------|------|---------------|
| **Il database** | MySQL/MariaDB (vedi `DB_DATABASE` e `DB_PREFIX` in `.env`) | Tutto ciò che è stato inserito nell'applicazione: territori, zone, assegnazioni, campagne, utenti, ruoli, pubblicazioni, poster, impostazioni. |
| **Il file `.env`** | Cartella principale dell'applicazione | La configurazione e i suoi segreti: la password del database, `APP_KEY` (che cifra, tra l'altro, l'autenticazione a due fattori degli utenti), le chiavi delle notifiche push (`VAPID_*`), `ARTISAN_CRON_TOKEN`. |
| **I file caricati** | `storage/app/private/territories/` | Le immagini dei territori (caricate o scaricate da un'importazione), raggiungibili solo tramite link temporanei. |
| | `storage/app/public/` | Le immagini dei poster (`posters/`), il logo (`branding/`) e le foto profilo (`profile-photos/`). |
| **Le chiavi degli assistenti IA** | `storage/oauth-private.key`, `storage/oauth-public.key` | Le chiavi che firmano le autorizzazioni degli [assistenti IA](setup#assistenti-ia): chiavi nuove scollegano tutti gli assistenti. |

Il modo più semplice per non dimenticare nulla è salvare sempre insieme **il database, il file `.env` e l'intera
cartella `storage/`**.

> ⚠️ **Attenzione** ⚠️
>
> Un backup contiene i dati personali della congregazione e i segreti dell'installazione: conservalo in un posto
> sicuro, lontano dal server (se si perde il server, si perdono anche i backup conservati lì), mai in una cartella
> raggiungibile dal web, ed elimina quelli vecchi che non ti servono più.

### Il database

**Con accesso alla shell**, esportalo con `mysqldump` (chiede la password del database):

```shell
mysqldump --single-transaction --default-character-set=utf8mb4 -u <utente> -p <database> > och-backup.sql
```

Se il database è condiviso con altre applicazioni o installazioni e le tabelle di questa hanno un prefisso
(`DB_PREFIX`, es. `och_`), esporta solo le tabelle che iniziano con esso (la password viene chiesta due volte):

```shell
mysqldump --single-transaction --default-character-set=utf8mb4 -u <utente> -p <database> \
    $(mysql -N -u <utente> -p <database> -e "SHOW TABLES LIKE 'och\_%'") > och-backup.sql
```

**Senza accesso alla shell** (es. su Altervista), usa lo strumento per il database del pannello dell'hosting, di solito
phpMyAdmin: apri il database, scegli **Esporta** e il formato **SQL**. Con un prefisso delle tabelle, scegli il metodo
di esportazione **Personalizzato** e seleziona solo le tabelle che iniziano con esso.

> 💡 **Suggerimento:** metti la data e la versione dell'applicazione nel nome del backup (es.
> `och-backup-2026-11-26-v1.0.0.sql`): la versione è mostrata in **Informazioni**, nel menu utente. Un backup va
> ripristinato con i file della stessa versione.

### I file

**Con accesso alla shell**, copia il file `.env` e la cartella `storage/`, ad esempio in un unico archivio:

```shell
tar -czf och-files.tar.gz .env storage/
```

**Senza accesso alla shell**, scaricali con un client FTP (es. FileZilla). Le cartelle `storage/logs/` e
`storage/framework/` contengono solo log, cache e sessioni: si possono tralasciare se il download richiede troppo
tempo.

### Ogni quanto

- **Il database**: almeno una volta alla settimana, più spesso se l'applicazione viene usata ogni giorno, e **sempre
  subito prima di un aggiornamento**.
- **I file caricati** (`storage/app/`): insieme al database, dato che il database vi fa riferimento.
- **Il file `.env` e le chiavi degli assistenti IA**: una volta dopo l'installazione, poi ogni volta che cambiano (es.
  una nuova password del database, o dopo aver attivato le notifiche push).

Conserva più di una copia (es. gli ultimi quattro backup settimanali): un problema notato in ritardo potrebbe essere già
nell'ultimo. Ogni tanto prova a ripristinare un backup su un'installazione di prova: è l'unico modo per sapere che
funziona.

### Ripristinare un backup

1. Se l'applicazione è in funzione, mettila in [modalità manutenzione](#modalità-manutenzione).
2. Assicurati che i file dell'applicazione siano quelli della **stessa versione** del backup (vedi
   [Aggiornare a una nuova versione](#aggiornare-a-una-nuova-versione) per sostituirli).
3. Rimetti al loro posto il file `.env` e la cartella `storage/` del backup.
4. Elimina le tabelle dell'installazione, poi importa il dump, così che non resti nessuna tabella creata dopo il backup:
    - **con accesso alla shell**, `php artisan db:wipe --force` elimina le tabelle dell'installazione (solo quelle che
      iniziano con `DB_PREFIX`, se è impostato), poi `mysql -u <utente> -p <database> < och-backup.sql` importa il
      dump;
    - **senza accesso alla shell**, in phpMyAdmin seleziona le tabelle dell'installazione (solo quelle che iniziano con
      il prefisso, se c'è), scegli **Elimina**, poi importa il dump dalla scheda **Importa**.
5. Ricrea il collegamento `public/storage` e le cache, che potrebbero riferirsi ai file precedenti, e controlla
   l'installazione (tramite `scripts/remote_artisan.sh` senza accesso alla shell, vedi sotto):

    ```shell
    php artisan och:post-deploy
    php artisan och:doctor
    ```

6. Esci dalla modalità manutenzione.

Tutto ciò che è stato inserito nell'applicazione dopo il backup va perso.

## Modalità manutenzione

Mentre i file o il database vengono modificati, la modalità manutenzione mostra ai visitatori una pagina "Service
Unavailable" invece di errori o di pagine aggiornate a metà:

```shell
# Attivarla
php artisan down --render="errors::503"

# Disattivarla
php artisan up
```

**Senza accesso alla shell**, attivala tramite l'endpoint Artisan, con `scripts/remote_artisan.sh` (vedi
[Aggiornare senza accesso alla shell](#aggiornare-senza-accesso-alla-shell)):

```shell
scripts/remote_artisan.sh --base-url https://och-instance.example.com \
    --ftp-hostname <host> --ftp-username <utente> --ftp-password <password> \
    down --render="errors::503"
```

> ⚠️ **Attenzione:** in modalità manutenzione anche l'endpoint Artisan non è disponibile, quindi `up` e gli altri
> comandi non possono essere eseguiti tramite esso. Per disattivare la modalità manutenzione, elimina il file
> `storage/framework/down` via FTP.

## Aggiornare a una nuova versione

### Prima di aggiornare

1. **Controlla la versione che hai**: è mostrata in **Informazioni**, nel menu utente.
2. **Leggi le note di rilascio** di tutte le versioni tra la tua e quella nuova, nella pagina
   [Releases](https://github.com/OpenCHelper/OpenCongregationHelper/releases) (sono le stesse del file `CHANGELOG.md`
   del progetto), e le [Note per versione](#note-per-versione) qui sotto: dicono cosa è cambiato e se devi fare
   qualcosa (es. una nuova impostazione in `.env`). Le versioni con un suffisso (es. `1.0.0-alpha15`) sono
   pre-release.
3. **Controlla i [requisiti](setup#requisiti)** della nuova versione (es. la versione di PHP).
4. **Fai il backup** del database, del file `.env` e della cartella `storage/` (vedi [Cosa salvare](#cosa-salvare)), e
   annota la versione a cui appartengono.

Scegli un momento in cui poche persone usano l'applicazione (es. la sera tardi): durante l'aggiornamento non è
disponibile.

### Aggiornare con accesso alla shell

1. Metti l'applicazione in modalità manutenzione:

    ```shell
    php artisan down --render="errors::503"
    ```

2. Sostituisci i file con quelli della nuova versione, mantenendo `.env` e `storage/`:
    - se l'applicazione è stata installata con Git, `git fetch --tags && git checkout v1.0.0` (con il tag della nuova
      versione);
    - altrimenti scarica l'archivio della nuova versione (**Source code** nella sua pagina di rilascio), estrailo in
      una nuova cartella, sposta `.env` e `storage/` al suo interno, poi scambiala con la vecchia cartella. Copiare i
      nuovi file sopra quelli vecchi lascerebbe i file che la nuova versione ha rimosso.
3. Installa le dipendenze PHP e compila gli asset del frontend, come per l'installazione:

    ```shell
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    ```

4. Aggiorna il database e il resto:

    ```shell
    php artisan och:post-deploy
    ```

    Esegue le nuove migrazioni, crea ciò che ancora manca (`och:install`: i nuovi permessi, le chiavi degli assistenti
    IA), ricrea il collegamento `public/storage`, svuota le cache della versione precedente e costruisce quelle nuove.
    Si ferma al primo passaggio che non riesce (vedi [Se l'aggiornamento non riesce](#se-laggiornamento-non-riesce)).
5. Esci dalla modalità manutenzione e, se ne usi uno, riavvia il worker della coda, così che carichi il nuovo codice:

    ```shell
    php artisan up
    php artisan queue:restart
    ```

6. [Controlla l'installazione](#dopo-laggiornamento).

### Aggiornare senza accesso alla shell

Sugli hosting condivisi senza accesso alla shell (es. Altervista), i file vengono caricati via FTP e i comandi eseguiti
tramite l'endpoint `/api/artisan/execute`, autorizzato da un token monouso che `scripts/remote_artisan.sh` carica via
FTP ed elimina dopo il comando. Lo script viene eseguito sul tuo computer: richiede `bash`, `curl`, `jq` e `lftp` (su
Windows, ad esempio in WSL). Se l'applicazione non è nella cartella principale dell'account FTP, indica il percorso del
suo token con `--token-file <cartella>/storage/framework/.artisan-token`.

1. **Sul tuo computer**, scarica l'archivio della nuova versione (**Source code** nella sua pagina di rilascio),
   estrailo, poi installa le dipendenze e compila gli asset nella sua cartella (servono PHP, Composer e Node.js):

    ```shell
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    ```

2. Metti l'applicazione in modalità manutenzione, dalla stessa cartella:

    ```shell
    scripts/remote_artisan.sh --base-url https://och-instance.example.com \
        --ftp-hostname <host> --ftp-username <utente> --ftp-password <password> \
        down --render="errors::503"
    ```

3. **Carica i file** via FTP, sostituendo quelli sul server:
    - non sovrascrivere mai il file `.env` e la cartella `storage/` del server, e non caricare la cartella
      `public/storage`, se il tuo computer ne ha una;
    - elimina dal server le cartelle che la nuova versione sostituisce (`app/`, `bootstrap/`, `config/`,
      `database/`, `docs/`, `lang/`, `resources/`, `routes/`, `vendor/`, e tutto il contenuto di `public/` tranne
      `public/storage`) prima di caricarle di nuovo, così che non resti nessun file rimosso dalla nuova versione.

    > ⚠️ **Attenzione:** non eliminare mai `public/storage` via FTP: è un collegamento a `storage/app/public/`, che un
    > client FTP può mostrare come una cartella, ed eliminarlo può eliminare i file caricati a cui porta.
    > `och:post-deploy` lo ricrea comunque.

4. Elimina il file `storage/framework/down` via FTP, per uscire dalla modalità manutenzione, poi **subito** aggiorna il
   database e il resto:

    ```shell
    scripts/remote_artisan.sh --base-url https://och-instance.example.com \
        --ftp-hostname <host> --ftp-username <utente> --ftp-password <password> \
        och:post-deploy --no-interaction
    ```

5. [Controlla l'installazione](#dopo-laggiornamento).

La coda e le attività pianificate continuano a funzionare tramite gli stessi endpoint (vedi
[Attività pianificate ed email](setup#attività-pianificate-ed-email)): lì non c'è nulla da cambiare.

### Dopo l'aggiornamento

- Esegui `och:doctor` (tramite `scripts/remote_artisan.sh` senza accesso alla shell): controlla la configurazione, il
  database e le sue migrazioni, il collegamento `public/storage`, le chiavi degli assistenti IA e i super
  amministratori, e dice cosa fare per ogni problema.
- Apri **Informazioni** nel menu utente: deve mostrare la nuova versione.
- Accedi e apri qualche pagina (es. un territorio con la sua mappa, un'assegnazione).

> ℹ️ **Nota:** la configurazione viene messa in cache da `och:post-deploy`. Se in seguito modifichi il file `.env` (es.
> per aggiungere una nuova impostazione), esegui di nuovo `och:post-deploy`, altrimenti la modifica viene ignorata.

## Se l'aggiornamento non riesce

`och:post-deploy` dice quale passaggio non è riuscito. I dettagli dell'errore sono scritti in
`storage/logs/laravel.log` (i super amministratori possono leggere i log dell'applicazione anche dal menu
**Sviluppatore**, se si apre ancora). Se la causa si può correggere (es. un'impostazione sbagliata in `.env`, un
database non raggiungibile), correggila ed esegui di nuovo `och:post-deploy`: si può eseguire quante volte serve, perché
esegue solo le migrazioni non ancora eseguite e crea solo ciò che manca.

Altrimenti, torna alla versione precedente:

1. Metti l'applicazione in [modalità manutenzione](#modalità-manutenzione).
2. Rimetti i file della versione precedente (mantenendo `.env` e `storage/`).
3. [Ripristina il backup](#ripristinare-un-backup) fatto prima dell'aggiornamento, poi esci dalla modalità
   manutenzione.

Tutto ciò che è stato inserito nell'applicazione tra l'aggiornamento e il ripristino va perso.

### Annullare le migrazioni

`php artisan migrate:rollback --force` annulla le migrazioni eseguite dall'ultimo `och:post-deploy`, senza ripristinare
un backup. È sicuro solo quando sono vere tutte queste condizioni:

- tutte le migrazioni dell'aggiornamento sono riuscite: se una si è interrotta a metà, MySQL non annulla la parte già
  fatta, e il rollback annullerebbe le migrazioni precedenti attorno a essa, lasciando il database in uno stato che
  nessuna versione si aspetta;
- viene eseguito **prima** di rimettere i file della versione precedente, perché le istruzioni per annullare le
  migrazioni sono nei file della nuova;
- le note di rilascio non dicono che annullare una migrazione fa perdere dati (es. una colonna o una tabella rimossa
  dalla nuova versione non può essere riempita di nuovo);
- nessuno ha ancora usato la nuova versione: ciò che è stato salvato nelle sue nuove colonne o tabelle andrebbe perso.

Poi rimetti i file della versione precedente ed esegui di nuovo `och:post-deploy`, per ricostruire le cache. Nel
dubbio, ripristina il backup.

## Note per versione

### Aggiornare alla 1.0.0 dalle versioni alpha

Leggi anche le note di rilascio di tutte le versioni alpha successive alla tua. Inoltre:

- **Database**: l'aggiornamento esegue tre nuove migrazioni, che si possono annullare tutte con `migrate:rollback`:
    - vengono eliminate le tabelle dei team (`teams`, `team_user`, `team_invitations`) e la colonna `current_team_id`
      della tabella `users`: l'applicazione non le ha mai usate e sono sempre vuote;
    - un'assegnazione può avere un solo link condiviso: se la stessa assegnazione ne aveva più di uno (condivisa due
      volte nello stesso momento), viene mantenuto solo il più vecchio;
    - il database rifiuta di eliminare un utente che ha delle assegnazioni, il cui storico andrebbe perso: disabilita
      invece il suo accesso (vedi [Utenti](administration/users)).
- **`TRUSTED_PROXIES`** (nuova, facoltativa): gli indirizzi dei proxy davanti all'applicazione. Il valore predefinito,
  `*`, si comporta come prima ed è necessario su Altervista: lì lascialo così. Se conosci l'indirizzo del tuo proxy,
  impostalo; se l'applicazione è raggiunta direttamente, senza proxy, lasciala vuota (`TRUSTED_PROXIES=`), così che i
  visitatori non possano spacciarsi per un altro indirizzo IP. Vedi
  [Impostazioni principali](setup#impostazioni-principali).
- **Password**: in produzione le nuove password devono avere almeno 12 caratteri e non comparire in una fuga di dati
  nota, verificata con Have I Been Pwned. Le password già impostate continuano a funzionare: le regole si applicano
  quando una password viene scelta, compresa `INITIAL_ROOT_PASSWORD` quando viene usata da `och:recover-root`
  (aggiornala in `.env` se non le rispetta). Se il server non riesce a raggiungere `api.pwnedpasswords.com`, imposta
  `PASSWORD_LEAK_CHECK=false`, oppure disattiva **Rifiuta le password compromesse** nelle
  [impostazioni di Sicurezza](administration/settings#sicurezza).
- **Accesso**: sono consentiti al massimo 10 tentativi con la stessa email ogni 15 minuti.
- **Dashboard**: le sue statistiche e mappe sono mostrate solo agli utenti che possono vedere i territori (**View Any**
  su *Territory*); gli altri vedono un messaggio di benvenuto. Controlla che i [ruoli](administration/roles) di chi deve
  vederle abbiano questo permesso.
- **Assegnazioni condivise**: **Condividi** non crea più un link da solo, il link va creato con **Crea link**, e i link
  delle assegnazioni già riconsegnate o eliminate smettono di funzionare.
- **API**: gli endpoint `/api/user`, `/api/assignees`, `/api/assignments`, `/api/territories` e `/api/zones` sono
  rimossi. Gli endpoint `/api/artisan` non cambiano.
