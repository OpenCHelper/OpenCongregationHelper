---
type: documentation
icon: heroicon-o-puzzle-piece
title: Guida al setup
---

## Requisiti

- PHP 8.2 o maggiore

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
