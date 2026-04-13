# Registro Territori

![Laravel](https://img.shields.io/badge/laravel-%23FF2D20.svg?style=for-the-badge&logo=laravel&logoColor=white)
[<img alt="Deployed with FTP Deploy Action" src="https://img.shields.io/badge/Deployed With-FTP DEPLOY ACTION-%3CCOLOR%3E?style=for-the-badge&color=blue">](https://github.com/SamKirkland/FTP-Deploy-Action)
[![Open in Dev Containers](https://img.shields.io/static/v1?label=Dev%20Containers&message=Open&color=blue)](https://vscode.dev/redirect?url=vscode://ms-vscode-remote.remote-containers/cloneInVolume?url=https://github.com/LukeSavefrogs/registro-territori)

## Support

To receive support and/or propose new features please open an issue.

## Changelog

Per la lista completa delle modifiche consultare il [**Changelog**](CHANGELOG.md).

## Development

The preferred way of developing is by using **Docker Compose** to build the containers that will be used to develop the application locally. If you don't want to use Docker, then it's up to you.

### Steps

1. Check that Apache is not running locally, otherwise stop it as it may interfere with the application.
2. Install the dependencies:
    ```shell
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php82-composer:latest \
        composer install --ignore-platform-reqs
    ```
3. If not present, create the `.env` file with the same contents as the `.env.example` file.
4. Build the local images:
    ```shell
    sail build --no-cache
    ```
5. Spin up the containers:
    ```shell
    sail up -d
    ```
6. Now we can start tweaking the application to make it work:
    1. Install dependencies:
        ```shell
        sail composer install
        ```
    2. Generate the [encryption key](https://laravel.com/docs/12.x/encryption) for the project:
        ```shell
        sail artisan key:generate
        ```
    3. Run database migrations:
        ```shell
        sail artisan migrate
        ```
    4. Seed initial data:
        ```shell
        sail artisan db:seed
        ```
    5. Seed dummy data for development:
        ```shell
        sail artisan db:seed --class=FakeDataSeeder
        ```
    6. Create super user:
        ```shell
        sail artisan shield:super-admin --user=1 --panel=app
        ```
7. Install the frontend dependencies and run the Vite server:
    ```shell
    sail npm install && sail npm run dev
    ```

## Deploy

### Altervista

#### Features

- 💾 Disk space: 3.072 MB (shared between file system and database)
- 🔒 HTTPS: Yes
- Traffic: 30 GB / month
- Subdomain: `{username}.altervista.org`
- PHP versions:
  - 5.6
  - 7.3
  - 8.0
  - 8.2
- 👨🏼‍💻 Shell access: No
