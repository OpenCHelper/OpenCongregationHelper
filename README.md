# Registro Territori

![Laravel](https://img.shields.io/badge/laravel-%23FF2D20.svg?style=for-the-badge&logo=laravel&logoColor=white)
[<img alt="Deployed with FTP Deploy Action" src="https://img.shields.io/badge/Deployed With-FTP DEPLOY ACTION-%3CCOLOR%3E?style=for-the-badge&color=blue">](https://github.com/SamKirkland/FTP-Deploy-Action)
[![Open in Dev Containers](https://img.shields.io/static/v1?label=Dev%20Containers&message=Open&color=blue)](https://vscode.dev/redirect?url=vscode://ms-vscode-remote.remote-containers/cloneInVolume?url=https://github.com/LukeSavefrogs/registro-territori)

## Tech stack

- Laravel 12
- Filament 4

## Support

To receive support and/or propose new features please open an issue.

## Changelog

For the full list of changes, please read the [**Changelog**](CHANGELOG.md).

## Development

The preferred way of developing is by using **Docker Compose** to build the containers that will be used to develop the application locally. If you don't want to use Docker, then it's up to you.

> [!NOTE]
> The steps listed in the following paragraph assume you are using [Laravel Sail](https://laravel.com/docs/12.x/sail) in a local environment.
>
> If you are working on this project using the provided DevContainer, just run the actual command (_usually, omitting `sail` before each command is enough_).
>
> For example:
> - Locally: `sail composer install`
> - DevContainer: `composer install`

### Steps

1. Install the dependencies:
    ```shell
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php82-composer:latest \
        composer install --ignore-platform-reqs
    ```
2. If not present, create the `.env` file with the same contents as the `.env.example` file.
3. Build the local images:
    ```shell
    sail build --no-cache
    ```
4. Spin up the containers:
    ```shell
    sail up -d
    ```

    > [!TIP]
    > Before starting the application, check that no other program (e.g. Apache) is using the port you set up for the application, as it may interfere with the application.

5. Now we can start tweaking the application to make it work:
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
6. Install the frontend dependencies and run the Vite server:
    ```shell
    sail npm install && sail npm run dev
    ```

### Run workflows locally

You can run the Github Action workflows locally using [`nektos/act`](https://github.com/nektos/act) (already included in the [DevContainer](./.devcontainer/devcontainer.json)).

For example, to only deploy the application using the nightly workflow:

```shell
act --rm --verbose=false \
    -s GITHUB_TOKEN="$(gh auth status &>/dev/null || gh auth login >&2; gh auth token)" \
    --pull=false \
    --rebuild=false \
    --use-gitignore=false \
    --workflows .github/workflows/upload-nightly.yml \
    --job 'upload' \
    --secret-file .act/.secrets \
    --env-file .act/.env \
    --var-file .act/.vars \
    --insecure-secrets
```

This command will run the `upload` job of the `.github/workflows/upload-nightly.yml` workflow:
- use [`gh`](https://cli.github.com/) to retrieve a token to authenticate Act against GitHub (_higher quotas and access to private repositories, may be omitted if not needed_).
- read the [workflow secrets](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets) (`${{ secrets.[*] }}`) from `.act/.secrets`
- read the [workflow environment variables](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-variables#defining-environment-variables-for-a-single-workflow) (`${{ env.[*] }}`) from `.act/.env`
- read the [workflow configuration variables](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-variables#defining-configuration-variables-for-multiple-workflows) (`${{ vars.[*] }}`) from `.act/.vars`

## Deploy

### Altervista

| Feature | Details |
| --- | --- |
| Disk space | 3.072 MB (shared between file system and database) |
| HTTPS | Yes |
| Traffic | 30 GB / month |
| Subdomain | `{username}.altervista.org` |
| PHP versions | 8.2<br/>8.0<br/>~~7.3~~ (_deprecated_)<br/>~~5.6~~ (_deprecated_) |
| Shell access | No |
