---
type: documentation
icon: heroicon-o-wrench-screwdriver
title: Setup guide
order: 0
---

This page is meant for whoever **installs and maintains** the application. If you only need to use it, start from the
**Basics** section.

## Requirements

- PHP 8.2 or newer, with the `pdo_mysql` (or `pdo_sqlite`), `gd`, `intl` and `zip` extensions;
- a MySQL/MariaDB database (SQLite works too, mostly for local development);
- Node.js, only to build the frontend assets (`npm run build`);
- a way to send emails (the native PHP `mail()` function is supported out of the box).

The application also runs on shared hosting without shell access (e.g. Altervista): see
[Scheduled tasks and emails](#scheduled-tasks-and-emails).

## Configuration

The application is configured using the `.env` file.

> ⚠️ **Warning** ⚠️
>
> Never ever submit the `.env` file to version control.

A minimal working configuration could look like the following:

```shell
APP_NAME="My Application Name"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://territories.example.com

# Choose here your default locale
APP_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=my_database_name
DB_USERNAME=my_database_username
DB_PASSWORD=my_database_password
DB_ENGINE=InnoDB

# This will be the root user
INITIAL_ROOT_USERNAME="Root User"
INITIAL_ROOT_USERMAIL="root@example.com"
INITIAL_ROOT_PASSWORD="password"
```

### Main settings

| Variable | Description |
|----------|-------------|
| `APP_URL` | Public URL of the application. Used in emails and in the links of shared assignments. |
| `APP_LOCALE` | Default language (`en`, `it` or `es`). Each user can then pick their own language. |
| `APP_TIMEZONE` | Timezone used to display dates. |
| `DB_PREFIX` / `DB_ENGINE` | Optional table prefix and storage engine, useful on shared databases: `db:wipe` and `migrate:fresh` then drop only the tables with this prefix. |
| `INITIAL_ROOT_USERNAME`, `INITIAL_ROOT_USERMAIL`, `INITIAL_ROOT_PASSWORD` | Name (first and last name separated by a space), email and password of the **root** user, created the first time the database is seeded. |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | How emails are sent. `failover` tries the native PHP `mail()` function first and falls back to the log. |
| `QUEUE_CONNECTION` | `database` (recommended in production) or `sync` (emails are sent immediately, during the request). |
| `ARTISAN_CRON_TOKEN` | Secret required by the endpoints that process the queue and the scheduled jobs. If empty, those endpoints are disabled. |
| `ENABLE_SHARED_PII` | When `true`, the public page of a shared assignment also shows the assignee's name and the territory notes. Defaults to `false`. |
| `DEMO_PREFILL_LOGIN_CREDENTIALS` | **Demo installations only**, with dummy data: when `true`, the login page is pre-filled with the credentials of the **root** user, so anyone can log in as root. Never enable it on an installation with real data. Always ignored when `APP_ENV` is `production`. Defaults to `false`. |
| `APP_VERSION_BRANCH_FILE`, `APP_VERSION_COMMIT_FILE` | Files used to show the Git branch and commit in the sidebar on servers without `git`. |

## Installation

1. Install the PHP dependencies and build the frontend assets:

    ```shell
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    ```

2. Create the `.env` file (see above) and generate the application key:

    ```shell
    php artisan key:generate
    ```

3. Create the database tables and the root user:

    ```shell
    php artisan migrate --force
    php artisan db:seed --force
    ```

4. Create the permissions and give the root user the `super_admin` role:

    ```shell
    php artisan db:seed --class=ShieldSeeder --force
    php artisan shield:super-admin --user=1 --panel=app
    ```

5. Open `/app/login` and log in with the `INITIAL_ROOT_*` credentials. Then follow the
   [Setting up the congregation](guides/first-setup) guide.

> 🔒 **The root user** is the user with ID 1. It can never be deleted nor impersonated and, outside of the `production`
> environment, its email, password, access and roles cannot be changed by anyone.

## Scheduled tasks and emails

Notification emails are queued and a weekly job sends the reminders for territories that have been out for a long time.
Both need something that runs periodically:

- **With shell access**, run the queue worker (`php artisan queue:work`) and add the Laravel scheduler to the crontab
  (`* * * * * php /path/to/artisan schedule:run`).
- **Without shell access** (shared hosting), set `ARTISAN_CRON_TOKEN` and configure an external cron service to call
  these endpoints, which are served under the `/api/artisan` path of `APP_URL`:

    ```shell
    # Process the queue (e.g. every 5 minutes)
    curl -X POST -H "X-Cron-Token: <your token>" https://territories.example.com/api/artisan/queue/process

    # Run the scheduled jobs (e.g. every hour)
    curl -X POST -H "X-Cron-Token: <your token>" https://territories.example.com/api/artisan/schedule/process
    ```

  The endpoints are rate limited (10 requests per minute).

## Push notifications

Push notifications are optional: until they are configured, the notifications are sent only by email and inside the
application, and the profile does not offer to enable them. To enable them, generate the keys once:

```shell
php artisan webpush:vapid
```

The command writes `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` to the `.env` file. Never change them afterwards, or every
user will have to enable the push notifications again. Optionally, set `VAPID_SUBJECT` to a contact of the
administrator (e.g. `mailto:admin@example.com`), given to the push services of the browsers (`APP_URL` when not set).

Push notifications need HTTPS and, like the emails, are sent by the queue (see above).

## Environments

On environments other than `production` (e.g. `local`, `nightly`, `staging`), super administrators see a colored
border and badge with the name of the environment, so they never confuse a test installation with the real one.
