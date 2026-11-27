---
type: documentation
icon: heroicon-o-circle-stack
title: Backup e ripristino
order: 0
---

Questa pagina è rivolta a chi **gestisce** un'installazione dell'applicazione: come farne il backup e ripristinarla.
Per installarla o aggiornarla, vedi la [Guida al setup](setup).

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
  subito prima di [aggiornare](setup#aggiornamento) l'applicazione**.
- **I file caricati** (`storage/app/`): insieme al database, dato che il database vi fa riferimento.
- **Il file `.env` e le chiavi degli assistenti IA**: una volta dopo l'installazione, poi ogni volta che cambiano (es.
  una nuova password del database, o dopo aver attivato le notifiche push).

Conserva più di una copia (es. gli ultimi quattro backup settimanali): un problema notato in ritardo potrebbe essere già
nell'ultimo. Ogni tanto prova a ripristinare un backup su un'installazione di prova: è l'unico modo per sapere che
funziona.

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
[Aggiornamento](setup#aggiornamento)):

```shell
scripts/remote_artisan.sh --base-url https://och-instance.example.com \
    --ftp-hostname <host> --ftp-username <utente> --ftp-password <password> \
    down --render="errors::503"
```

> ⚠️ **Attenzione:** in modalità manutenzione anche l'endpoint Artisan non è disponibile, quindi `up` e gli altri
> comandi non possono essere eseguiti tramite esso. Per disattivare la modalità manutenzione, elimina il file
> `storage/framework/down` via FTP.

## Ripristinare un backup

1. Se l'applicazione è in funzione, mettila in [modalità manutenzione](#modalità-manutenzione).
2. Assicurati che i file dell'applicazione siano quelli della **stessa versione** del backup: se non lo sono, rimetti i
   file di quella versione, mantenendo `.env` e `storage/` (vedi [Aggiornamento](setup#aggiornamento)).
3. Rimetti al loro posto il file `.env` e la cartella `storage/` del backup, senza eliminare né sovrascrivere il file
   `storage/framework/down`, che tiene attiva la modalità manutenzione.
4. Elimina le tabelle dell'installazione, poi importa il dump, così che non resti nessuna tabella creata dopo il backup:
    - **con accesso alla shell**, `php artisan db:wipe --force` elimina le tabelle dell'installazione (solo quelle che
      iniziano con `DB_PREFIX`, se è impostato), poi `mysql -u <utente> -p <database> < och-backup.sql` importa il
      dump;
    - **senza accesso alla shell**, in phpMyAdmin seleziona le tabelle dell'installazione (solo quelle che iniziano con
      il prefisso, se c'è), scegli **Elimina**, poi importa il dump dalla scheda **Importa**.
5. Ricrea il collegamento `public/storage` e le cache, che potrebbero riferirsi ai file precedenti, poi controlla
   l'installazione ed esci dalla modalità manutenzione:
    - **con accesso alla shell**:

        ```shell
        php artisan och:post-deploy
        php artisan och:doctor
        php artisan up
        ```

    - **senza accesso alla shell**, in modalità manutenzione l'endpoint Artisan non è disponibile: prima elimina il
      file `storage/framework/down` via FTP, poi esegui subito `och:post-deploy` e `och:doctor` con
      `scripts/remote_artisan.sh` (vedi [Aggiornamento](setup#aggiornamento)).

Tutto ciò che è stato inserito nell'applicazione dopo il backup va perso.
