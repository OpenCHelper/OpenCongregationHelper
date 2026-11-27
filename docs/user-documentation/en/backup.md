---
type: documentation
icon: heroicon-o-arrow-path
title: Backup and upgrade
order: 0
---

This page is meant for whoever **maintains** an installation of the application: how to back it up, restore it, and
upgrade it to a new version. To install it the first time, see the [Setup guide](setup).

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
  an upgrade**.
- **The uploaded files** (`storage/app/`): together with the database, since the database refers to them.
- **The `.env` file and the keys of the AI assistants**: once after the installation, then every time they change
  (e.g. a new password of the database, or after enabling the push notifications).

Keep more than one copy (e.g. the last four weekly backups): a problem noticed late may be already in the latest one.
From time to time, try to restore a backup on a test installation: it is the only way to know that it works.

### Restoring a backup

1. If the application is running, put it in [maintenance mode](#maintenance-mode).
2. Make sure the files of the application are those of the **same version** as the backup (see
   [Upgrading to a new version](#upgrading-to-a-new-version) to replace them).
3. Put back the `.env` file and the `storage/` folder of the backup, without deleting or overwriting the
   `storage/framework/down` file, which keeps the maintenance mode on.
4. Delete the tables of the installation, then import the dump, so that no table created after the backup is left
   behind:
    - **with shell access**, `php artisan db:wipe --force` drops the tables of the installation (only those starting
      with `DB_PREFIX`, when it is set: before version 1.0.0-alpha13 it drops **every** table of the database, so on a
      shared database drop them with the `mysql` client instead), then `mysql -u <user> -p <database> < och-backup.sql`
      imports the dump;
    - **without shell access**, in phpMyAdmin select the tables of the installation (only those starting with the
      prefix, if any), choose **Drop**, then import the dump from the **Import** tab.
5. Rebuild the `public/storage` link and the caches, which may refer to the previous files, then check the
   installation and leave the maintenance mode:
    - **with shell access**:

        ```shell
        php artisan och:post-deploy
        php artisan och:doctor
        php artisan up
        ```

    - **without shell access**, the Artisan endpoint is unavailable in maintenance mode: first delete the
      `storage/framework/down` file through FTP, then run `och:post-deploy` and `och:doctor` right away with
      `scripts/remote_artisan.sh` (see [Upgrading without shell access](#upgrading-without-shell-access)).

    `och:post-deploy` and `och:doctor` exist from version 1.0.0-alpha15: with the files of an older version, follow
    the **Updating** section of its own Setup guide instead.

Everything that was entered in the application after the backup is lost.

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
[Upgrading without shell access](#upgrading-without-shell-access)):

```shell
scripts/remote_artisan.sh --base-url https://och-instance.example.com \
    --ftp-hostname <host> --ftp-username <user> --ftp-password <password> \
    down --render="errors::503"
```

> ⚠️ **Warning:** in maintenance mode the Artisan endpoint is unavailable too, so `up` and the other commands cannot
> be run through it. To turn the maintenance mode off, delete the `storage/framework/down` file through FTP.

## Upgrading to a new version

### Before upgrading

1. **Check the version you have**: it is shown in **About**, in the user menu.
2. **Read the release notes** of every version between yours and the new one, on the
   [Releases](https://github.com/OpenCHelper/OpenCongregationHelper/releases) page (they are the same as the
   `CHANGELOG.md` file of the project), and the [Version-specific notes](#version-specific-notes) below: they say what
   changed, and whether you need to do something (e.g. a new setting in `.env`). The versions with a suffix (e.g.
   `1.0.0-alpha15`) are pre-releases.
3. **Check the [requirements](setup#requirements)** of the new version (e.g. the PHP version).
4. **Back up** the database, the `.env` file and the `storage/` folder (see [What to back up](#what-to-back-up)), and
   write down the version they belong to.

Choose a moment when few people use the application (e.g. late in the evening): it is unavailable during the upgrade.

### Upgrading with shell access

1. Put the application in maintenance mode:

    ```shell
    php artisan down --render="errors::503"
    ```

2. Replace the files with those of the new version, keeping `.env` and `storage/`:
    - if the application was installed with Git, `git fetch --tags && git checkout v1.0.0` (with the tag of the new
      version);
    - otherwise, download the archive of the new version (**Source code** on its release page), unpack it in a new
      folder, move `.env` and `storage/` into it, then swap it with the old folder. Copying the new files over the old
      ones would leave behind the files the new version removed.
3. Install the PHP dependencies and build the frontend assets, as for the installation:

    ```shell
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    ```

4. Update the database and the rest:

    ```shell
    php artisan och:post-deploy
    ```

    It runs the new migrations, creates what is still missing (`och:install`: the new permissions, the keys of the AI
    assistants), recreates the `public/storage` link, clears the caches of the previous version and builds the new
    ones. It stops at the first step that fails (see [If the upgrade fails](#if-the-upgrade-fails)).
5. Leave the maintenance mode, and restart the queue worker if you run one, so that it loads the new code:

    ```shell
    php artisan up
    php artisan queue:restart
    ```

6. [Check the installation](#after-upgrading).

### Upgrading without shell access

On shared hosting without shell access (e.g. Altervista), the files are uploaded through FTP and the commands run
through the `/api/artisan/execute` endpoint, authorized by a one-time token that `scripts/remote_artisan.sh` uploads
through FTP and deletes after the command. The script runs on your computer: it needs `bash`, `curl`, `jq` and `lftp`
(on Windows, e.g. in WSL; with a version older than 1.0.0, run it as `bash scripts/remote_artisan.sh` on macOS). If
the application is not in the root folder of the FTP account, give the path of its token with
`--token-file <folder>/storage/framework/.artisan-token`.

1. **On your computer**, download the archive of the new version (**Source code** on its release page), unpack it,
   then install the dependencies and build the assets in its folder (you need PHP, Composer and Node.js):

    ```shell
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    ```

2. Put the application in maintenance mode, from the same folder:

    ```shell
    scripts/remote_artisan.sh --base-url https://och-instance.example.com \
        --ftp-hostname <host> --ftp-username <user> --ftp-password <password> \
        down --render="errors::503"
    ```

3. **Upload the files** through FTP, replacing those on the server:
    - never overwrite the `.env` file and the `storage/` folder of the server, and do not upload the `public/storage`
      folder, if your computer has one;
    - do not upload what is only needed to build or develop the application: `node_modules/` (tens of thousands of
      files), `tests/`, `.git/` and `.github/`;
    - delete from the server the folders that the new version replaces (`app/`, `bootstrap/`, `config/`,
      `database/`, `docs/`, `lang/`, `resources/`, `routes/`, `vendor/`, and everything in `public/` except
      `public/storage`) before uploading them again, so that no file removed by the new version is left behind.

    > ⚠️ **Warning:** never delete `public/storage` through FTP: it is a link to `storage/app/public/`, which an FTP
    > client may show as a folder, and deleting it may delete the uploaded files it leads to. `och:post-deploy`
    > recreates it anyway.
4. Delete the `storage/framework/down` file through FTP, to leave the maintenance mode, then **right away** update the
   database and the rest:

    ```shell
    scripts/remote_artisan.sh --base-url https://och-instance.example.com \
        --ftp-hostname <host> --ftp-username <user> --ftp-password <password> \
        och:post-deploy --no-interaction
    ```

    From now on the application is online: if `och:post-deploy` fails, put it back in maintenance mode right away
    (step 2) before looking for the cause (see [If the upgrade fails](#if-the-upgrade-fails)).
5. [Check the installation](#after-upgrading).

The queue and the scheduled jobs keep running through the same endpoints (see
[Scheduled tasks and emails](setup#scheduled-tasks-and-emails)): nothing to change there.

### After upgrading

- Run `och:doctor` (through `scripts/remote_artisan.sh` without shell access): it checks the configuration, the
  database and its migrations, the `public/storage` link, the keys of the AI assistants and the super administrators,
  and tells what to do about each problem.
- Open **About** in the user menu: it must show the new version.
- Log in and open a few pages (e.g. a territory with its map, an assignment).

> ℹ️ **Note:** the configuration is cached by `och:post-deploy`. If you change the `.env` file afterwards (e.g. to add
> a new setting), run `och:post-deploy` again, or the change is ignored.

## If the upgrade fails

`och:post-deploy` tells which step failed. The details of the error are written in `storage/logs/laravel.log` (the
super administrators can also read the logs of the application from the **Developer** menu, if it still opens). If
the cause can be fixed (e.g. a wrong setting in `.env`, a database not reachable), fix it and run `och:post-deploy`
again: it can run any number of times, as it only runs the migrations not run yet and creates only what is missing.

Otherwise, go back to the previous version:

1. Put the application in [maintenance mode](#maintenance-mode).
2. Put back the files of the previous version (keeping `.env` and `storage/`).
3. [Restore the backup](#restoring-a-backup) taken before the upgrade, then leave the maintenance mode.

Anything entered in the application between the upgrade and the restore is lost.

### Undoing the migrations

`php artisan migrate:rollback --force` undoes the migrations run by the last `och:post-deploy`, without restoring a
backup. If `och:post-deploy` ran more than once during the upgrade (e.g. again after fixing an error), the last run
holds only the migrations it ran itself: `--step=<number>` undoes that number of migrations instead, the last ones
first. It is safe only when all of the following are true:

- all the migrations of the upgrade succeeded: if one failed halfway, MySQL does not undo the part already done, and
  the rollback would undo the previous migrations around it, leaving the database in a state no version expects;
- it runs **before** putting back the files of the previous version, since the instructions to undo the migrations
  are in the files of the new one;
- the release notes do not say that undoing a migration loses data (e.g. a column or a table removed by the new
  version cannot be filled again);
- nobody has used the new version yet: what was saved in its new columns or tables would be lost.

Then put back the files of the previous version and run `och:post-deploy` again, to rebuild the caches. When in doubt,
restore the backup instead.

## Version-specific notes

### Upgrading to 1.0.0 from the alpha versions

Read the release notes of every alpha version after yours too. In addition:

- **Going back to an alpha version**: `och:post-deploy` and `och:doctor` exist from 1.0.0-alpha15, and before
  1.0.0-alpha13 `db:wipe` drops every table of the database, whatever `DB_PREFIX`. To restore or roll back to an older
  version, follow the Setup guide of that version, and drop the tables by hand (with phpMyAdmin or the `mysql` client).

- **Database**: the upgrade runs four new migrations, which can all be undone with `migrate:rollback`:
    - the tables of the teams (`teams`, `team_user`, `team_invitations`) and the `current_team_id` column of the
      `users` table are dropped: the application never used them, and they are always empty;
    - an assignment can have only one shared link: if the same assignment had more (shared twice at the same time),
      only the oldest one is kept, and the others are deleted for good (undoing the migration does not bring them
      back);
    - the new **Refuse compromised passwords** setting is added (see **Passwords** below);
    - the database refuses to delete a user who has assignments, whose history would be lost: disable their access
      instead (see [Users](administration/users)).
- **`TRUSTED_PROXIES`** (new, optional): the addresses of the proxies in front of the application. The default, `*`,
  behaves as before and is needed on Altervista: keep it there. If you know the address of your proxy, set it; if the
  application is reached directly, without a proxy, set it empty (`TRUSTED_PROXIES=`), so that visitors cannot pass
  themselves off as another IP address. See [Main settings](setup#main-settings).
- **Passwords**: in production the new passwords need at least 12 characters and must not appear in a known data
  leak, checked with Have I Been Pwned. The passwords already set keep working: the rules apply when a password is
  chosen, including `INITIAL_ROOT_PASSWORD` when it is used by `och:recover-root` (update it in `.env` if it does not
  follow them). If the server cannot reach `api.pwnedpasswords.com`, set `PASSWORD_LEAK_CHECK=false`, or disable
  **Refuse compromised passwords** in the [Security settings](administration/settings#security).
- **Login**: at most 10 attempts with the same email every 15 minutes are allowed.
- **Dashboard**: its statistics and maps are shown only to the users who can view the territories (**View Any** on
  *Territory*); the others see a welcome message. Check that the [roles](administration/roles) of who should see them
  have this permission.
- **Shared assignments**: **Share** no longer creates a link by itself, the link must be created with **Create link**,
  and the links of the assignments already returned or deleted stop working.
- **API**: the `/api/user`, `/api/assignees`, `/api/assignments`, `/api/territories` and `/api/zones` endpoints are
  removed. The `/api/artisan` endpoints are unchanged.
