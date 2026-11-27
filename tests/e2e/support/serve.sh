#!/usr/bin/env bash
#
# Prepare an isolated instance of the application and serve it, for the end-to-end tests (see `playwright.config.ts`).
#
#   tests/e2e/support/serve.sh <instance> <port>
#
# `setup` is left empty, for the setup wizard; any other instance (`app`) is installed and seeded with `E2eSeeder`. Each
# instance has its own `.env.e2e-<instance>` (picked by `APP_ENV`), storage directory and database, so that it never
# touches the `.env`, storage and database of development.
#
# The database comes from `E2E_DB_CONNECTION` (`sqlite`, the default, `mysql`, `mariadb` or `pgsql`) and, except for
# SQLite, `E2E_DB_HOST`, `E2E_DB_PORT`, `E2E_DB_USERNAME` and `E2E_DB_PASSWORD`: the database `e2e_<instance>` is
# created when missing.

set -euo pipefail

instance="${1:?Usage: serve.sh <instance> <port>}"
port="${2:?Usage: serve.sh <instance> <port>}"

cd "$(dirname "$0")/../../.."

export APP_ENV="e2e-${instance}"
export LARAVEL_STORAGE_PATH="${PWD}/storage/e2e/${instance}"

connection="${E2E_DB_CONNECTION:-sqlite}"
env_file=".env.${APP_ENV}"

rm -rf "${LARAVEL_STORAGE_PATH}"
mkdir -p "${LARAVEL_STORAGE_PATH}"/{app/public,framework/{cache/data,sessions,testing,views},logs}

if [[ "${connection}" == "sqlite" ]]; then
    database="${LARAVEL_STORAGE_PATH}/database.sqlite"
    touch "${database}"
    # Many requests write at once (parallel tests, several server workers): they wait for each other
    db_variables="DB_CONNECTION=sqlite
DB_DATABASE=\"${database}\"
DB_BUSY_TIMEOUT=15000
DB_JOURNAL_MODE=wal"
else
    database="e2e_${instance}"
    db_variables="DB_CONNECTION=${connection}
DB_HOST=${E2E_DB_HOST:-127.0.0.1}
DB_PORT=${E2E_DB_PORT:-$([[ "${connection}" == "pgsql" ]] && echo 5432 || echo 3306)}
DB_DATABASE=${database}
DB_USERNAME=${E2E_DB_USERNAME:-root}
DB_PASSWORD=\"${E2E_DB_PASSWORD:-}\""
fi

# The setup wizard asks for the database itself: its instance starts without one
if [[ "${instance}" == "setup" ]]; then
    db_variables=""
fi

cat > "${env_file}" <<ENV
APP_NAME="OCH E2E"
APP_ENV=${APP_ENV}
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://127.0.0.1:${port}
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
ARTISAN_CRON_TOKEN=e2e-cron-token
ENABLE_SHARED_PII=true
GUEST_ALLOW_RETURNING_ASSIGNMENT=true
TRUSTED_PROXIES="*"
BCRYPT_ROUNDS=4
LOG_CHANNEL=single
LOG_LEVEL=debug
PASSWORD_LEAK_CHECK=false
${db_variables}
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_COOKIE=och_e2e_${instance}_session
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=file
MAIL_MAILER=log
MAIL_FROM_ADDRESS="e2e@example.com"
MAIL_FROM_NAME="OCH E2E"
SETUP_WIZARD=true
SETUP_TOKEN=e2e-setup-token
INITIAL_ROOT_USERNAME="Root User"
INITIAL_ROOT_USERMAIL="root@e2e.test"
INITIAL_ROOT_PASSWORD="RootPassword123"
DEMO_PREFILL_LOGIN_CREDENTIALS=false
ENV

php artisan key:generate --force --no-interaction > /dev/null

if [[ "${connection}" != "sqlite" ]]; then
    # A database of its own, emptied at every start
    php -r '
        [$driver, $host, $port, $user, $password, $name] = array_slice($argv, 1);
        $pdo = new PDO($driver === "pgsql" ? "pgsql:host=$host;port=$port;dbname=postgres" : "mysql:host=$host;port=$port", $user, $password);
        $pdo->exec("DROP DATABASE IF EXISTS $name");
        $pdo->exec("CREATE DATABASE $name");
    ' "${connection}" "${E2E_DB_HOST:-127.0.0.1}" "${E2E_DB_PORT:-$([[ "${connection}" == "pgsql" ]] && echo 5432 || echo 3306)}" \
        "${E2E_DB_USERNAME:-root}" "${E2E_DB_PASSWORD:-}" "${database}"
fi

if [[ "${instance}" != "setup" ]]; then
    php artisan migrate:fresh --force --no-interaction > /dev/null
    php artisan och:install --no-interaction > /dev/null
    php artisan db:seed --class=E2eSeeder --force --no-interaction > /dev/null
    # The scripts and styles of Filament (in `public/`, shared by the instances)
    php artisan filament:assets --no-interaction > /dev/null
fi

# Several workers, so that the parallel tests (and the requests of a page) don't queue up behind each other. The
# server of PHP is started directly, from `public/` (as `php artisan serve` does), to fill `$_ENV`, where Laravel reads
# `LARAVEL_STORAGE_PATH` from.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-6}"
#
# Its log of each connection and request is left out (as `php artisan serve` does), keeping the errors, also written
# to `logs/server.log` of the instance (e.g. a crash of the server, kept with the test results on CI).
cd public
exec php -d variables_order=EGPCS -S "127.0.0.1:${port}" ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php \
    2> >(grep --line-buffered -vE '^\[[0-9]+\] \[[^]]+\] (127\.0\.0\.1:[0-9]+ (Accepted|Closing|\[[0-9]{3}\]: )|PHP [0-9.]+ Development Server|Failed to poll event|Closed without sending a request)' | tee -a "${LARAVEL_STORAGE_PATH}/logs/server.log" >&2)
