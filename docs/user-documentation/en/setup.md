---
type: documentation
icon: heroicon-o-puzzle-piece
title: Setup guide
---

## Requirements

- PHP 8.2 or newer

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
