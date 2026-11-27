---
type: documentation
icon: heroicon-o-circle-stack
title: Backup and restore
order: 0
---

This page is meant for whoever **maintains** an installation of the application: how to back it up and restore it. To
install or update it, see the [Setup guide](setup).

## What to back up

An installation is made of its code, which can always be downloaded again, and of its data, which exists only on your
server:

| What | Where | What it holds |
|------|-------|---------------|
| **The database** | MySQL/MariaDB (see `DB_DATABASE` and `DB_PREFIX` in `.env`) | Everything entered in the application: territories, zones, assignments, campaigns, users, roles, publications, posters, settings. |
| **The `.env` file** | Root folder of the application | The configuration and its secrets: the database password, `APP_KEY` (which encrypts, among other things, the two-factor authentication of the users), the keys of the push notifications (`VAPID_*`), `ARTISAN_CRON_TOKEN`. |
| **The uploaded files** | `storage/app/private/territories/` | The territory images (uploaded or downloaded by an import), only reachable through temporary links. |
| | `storage/app/public/` | The pictures of the posters (`posters/`), the logo (`branding/`) and the profile photos (`profile-photos/`). |
| **The keys of the AI assistants** | `storage/oauth-private.key`, `storage/oauth-public.key` | The keys signing the authorizations of the [AI assistants](setup#ai-assistants): new keys disconnect every assistant. |

The simplest way to never forget anything is to always back up **the database, the `.env` file and the whole
`storage/` folder** together.

> ⚠️ **Warning** ⚠️
>
> A backup contains the personal data of the congregation and the secrets of the installation: keep it in a safe
> place, away from the server (if the server is lost, so are the backups kept there), never in a folder reachable
> from the web, and delete the old ones you no longer need.

### The database

**With shell access**, export it with `mysqldump` (it asks for the password of the database):

```shell
mysqldump --single-transaction --default-character-set=utf8mb4 -u <user> -p <database> > och-backup.sql
```

If the database is shared with other applications or installations and the tables of this one have a prefix
(`DB_PREFIX`, e.g. `och_`), export only the tables starting with it (the password is asked twice):

```shell
mysqldump --single-transaction --default-character-set=utf8mb4 -u <user> -p <database> \
    $(mysql -N -u <user> -p <database> -e "SHOW TABLES LIKE 'och\_%'") > och-backup.sql
```

**Without shell access** (e.g. on Altervista), use the database tool of the hosting panel, usually phpMyAdmin: open the
database, choose **Export** and the **SQL** format. With a table prefix, choose the **Custom** export method and select
only the tables starting with it.

> 💡 **Tip:** put the date and the version of the application in the name of the backup (e.g.
> `och-backup-2026-11-26-v1.0.0.sql`): the version is shown in **About**, in the user menu. A backup must be restored
> with the files of the same version.

### The files

**With shell access**, copy the `.env` file and the `storage/` folder, e.g. in a single archive:

```shell
tar -czf och-files.tar.gz .env storage/
```

**Without shell access**, download them with an FTP client (e.g. FileZilla). The `storage/logs/` and
`storage/framework/` folders hold only logs, caches and sessions: they can be left out if the download takes too long.

### How often

- **The database**: at least once a week, more often if the application is used every day, and **always right before
  [updating](setup#updating) the application**.
- **The uploaded files** (`storage/app/`): together with the database, since the database refers to them.
- **The `.env` file and the keys of the AI assistants**: once after the installation, then every time they change
  (e.g. a new password of the database, or after enabling the push notifications).

Keep more than one copy (e.g. the last four weekly backups): a problem noticed late may be already in the latest one.
From time to time, try to restore a backup on a test installation: it is the only way to know that it works.

## Maintenance mode

While the files or the database are being changed, the maintenance mode shows the visitors a "Service Unavailable"
page instead of errors or half-updated pages:

```shell
# Turn it on
php artisan down --render="errors::503"

# Turn it off
php artisan up
```

**Without shell access**, turn it on through the Artisan endpoint, with `scripts/remote_artisan.sh` (see
[Updating](setup#updating)):

```shell
scripts/remote_artisan.sh --base-url https://och-instance.example.com \
    --ftp-hostname <host> --ftp-username <user> --ftp-password <password> \
    down --render="errors::503"
```

> ⚠️ **Warning:** in maintenance mode the Artisan endpoint is unavailable too, so `up` and the other commands cannot
> be run through it. To turn the maintenance mode off, delete the `storage/framework/down` file through FTP.

## Restoring a backup

1. If the application is running, put it in [maintenance mode](#maintenance-mode).
2. Make sure the files of the application are those of the **same version** as the backup: if they are not, put back
   the files of that version, keeping `.env` and `storage/` (see [Updating](setup#updating)).
3. Put back the `.env` file and the `storage/` folder of the backup, without deleting or overwriting the
   `storage/framework/down` file, which keeps the maintenance mode on.
4. Delete the tables of the installation, then import the dump, so that no table created after the backup is left
   behind:
    - **with shell access**, `php artisan config:clear` makes the application read the `.env` file just put back
      (instead of the cached settings), `php artisan db:wipe --force` drops the tables of the installation (only those
      starting with `DB_PREFIX`, when it is set), then `mysql -u <user> -p <database> < och-backup.sql` imports the
      dump;
    - **without shell access**, in phpMyAdmin select the tables of the installation (only those starting with the
      prefix, if any) and choose **Drop**, unticking **Enable foreign key checks** in the confirmation (otherwise some
      tables may refuse to be dropped), then import the dump from the **Import** tab.
5. Empty the queue (the `jobs` table): the jobs saved in the backup were most likely already processed after it, and
   would send their emails and notifications a second time. With shell access run `php artisan queue:clear --force`;
   without, in phpMyAdmin choose **Empty** on the `jobs` table (with its prefix, if any).
6. Rebuild the `public/storage` link and the caches, which may refer to the previous files, then check the
   installation and leave the maintenance mode:
    - **with shell access**:

        ```shell
        php artisan och:post-deploy
        php artisan och:doctor
        php artisan up
        ```

    - **without shell access**, the Artisan endpoint is unavailable in maintenance mode: first delete the
      `storage/framework/down` file through FTP, then run `och:post-deploy` and `och:doctor` right away with
      `scripts/remote_artisan.sh` (see [Updating](setup#updating)).

Everything that was entered in the application after the backup is lost.
