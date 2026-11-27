# Contributing

Thanks for helping! Report bugs and propose features or enhancements with the [issue
templates](https://github.com/OpenCHelper/OpenCongregationHelper/issues/new/choose); report security problems
privately, as explained in the [security policy](.github/SECURITY.md), never with real congregation data. Contributions
are released under the [MIT license](LICENSE).

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
2. If not present, create the `.env` file with the same contents as the `.env.example` file, then set `APP_ENV=local`, `APP_DEBUG=true` and `LOG_LEVEL=debug` in it (the example is production-safe).
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
    4. Create the permissions, the keys of the AI assistants (MCP server) and the root user (from the
       `INITIAL_ROOT_*` variables) with the super admin role:
        ```shell
        sail artisan och:install
        ```
    5. Seed dummy data for development:
        ```shell
        sail artisan db:seed --class=FakeDataSeeder
        ```
6. Install the frontend dependencies and run the Vite server:
    ```shell
    sail npm install && sail npm run dev
    ```

Without Sail, `composer run dev` starts the web server, the queue worker, the log viewer (`pail`) and Vite together.

### End-to-end tests

The browser tests in [`tests/e2e`](./tests/e2e) use [Playwright](https://playwright.dev) on two instances of the
application, each with its own `.env.e2e-*` file, storage (`storage/e2e/`) and database, so they never touch the
development ones: `app`, installed and seeded with `E2eSeeder` (a user for each role, password `password`), and
`setup`, empty, for the setup wizard. They are served by
[`tests/e2e/support/serve.sh`](./tests/e2e/support/serve.sh) on the ports 8010 and 8011.

They run on Chromium (desktop), and the ones tagged `@mobile` in their title also on a phone: an Android one (Pixel 7,
Chromium) and an iPhone (iPhone 14, WebKit, the engine of Safari and of every browser on iOS). Run the tests of one of
them with `npx playwright test --project=<name>` (`chromium`, `mobile` or `mobile-webkit`).

1. Build the frontend assets and install the browsers (once):
    ```shell
    npm run build && npx playwright install chromium webkit
    ```
2. Run the tests (`npm run test:e2e:ui` opens the interactive runner):
    ```shell
    npm run test:e2e
    ```

They use SQLite by default: set `E2E_DB_CONNECTION` (`mysql`, `mariadb` or `pgsql`) and `E2E_DB_HOST`, `E2E_DB_PORT`,
`E2E_DB_USERNAME`, `E2E_DB_PASSWORD` to run them on another database (the databases `e2e_app` and `e2e_setup` are
created). Outside of CI, servers already running on the ports are reused: stop them to start from fresh data. The
`E2E tests` workflow (`run-e2e-tests.yml`) runs them on MySQL, and the setup wizard on SQLite and MySQL: the support of
the databases is tested by the `Pest tests` workflow (`run-pest-tests.yml`), on MySQL and SQLite, each with and
without a table prefix.

### Run workflows locally

You can run the Github Action workflows locally using [`nektos/act`](https://github.com/nektos/act) (already included in the [DevContainer](./.devcontainer/devcontainer.json)).

For example, to deploy the preview of a pull request:

```shell
act --rm --verbose=false \
    -s GITHUB_TOKEN="$(gh auth status &>/dev/null || gh auth login >&2; gh auth token)" \
    --pull=false \
    --rebuild=false \
    --workflows .github/workflows/deploy-preview.yml \
    --job 'deploy' \
    --input action=deploy --input pr=123 \
    --secret-file .act/.secrets \
    --env-file .act/.env \
    --var-file .act/.vars \
    --insecure-secrets
```

This command will run the `deploy` job of the `.github/workflows/deploy-preview.yml` workflow:
- use [`gh`](https://cli.github.com/) to retrieve a token to authenticate Act against GitHub (_higher quotas and access to private repositories, may be omitted if not needed_).
- read the [workflow secrets](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets) (`${{ secrets.[*] }}`) from `.act/.secrets`
- read the [workflow environment variables](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-variables#defining-environment-variables-for-a-single-workflow) (`${{ env.[*] }}`) from `.act/.env`
- read the [workflow configuration variables](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-variables#defining-configuration-variables-for-multiple-workflows) (`${{ vars.[*] }}`) from `.act/.vars`

The deploy workflows read the settings of their GitHub environment (e.g. `PREVIEW`, see
[Deploying](README.md#deploying) in the README) and the repository variables. Locally, `act` reads all of them from
the files above, under the same names whatever the environment, so keep one pair of files for each space (both are
ignored by git):

```dotenv
# .act/.vars
FTP_SERVER=ftp.{username}.altervista.org
FTP_USERNAME={username}

# Router of the space
SPACE_DEFAULT_ENVIRONMENT=demo
SPACE_PHP_HANDLER=av-php82
```

```dotenv
# .act/.secrets
FTP_PASSWORD=password

# PREVIEW only: password of the root user of the previews, and API key of cron-job.org
ROOT_PASSWORD=MyVerySecretPassword
CRON_JOB_ORG_API_KEY=MyCronJobOrgApiKey
```

## Workflow

- Work on a branch and open a pull request to `main`; never force-push `main`.
- Write commit messages and pull request titles in the [Conventional Commits](https://www.conventionalcommits.org)
  style (e.g. `fix(users): keep a super admin`), following the 50/72 rule: a title of at most 50 characters and a body
  wrapped at 72. Keep them short: they say what changed, not the whole story.
- Add `[skip ci]` to the title of commits that only change documentation, unless they touch
  `docs/user-documentation/` (shipped in the app). While the last commit of a pull request has it, GitHub runs none of
  its workflows, so the required checks are missing and the pull request cannot be merged until a later commit.
- Each pull request can get a preview, deployed and removed with the `preview: on` and `preview: off` labels (see
  [Altervista](README.md#altervista) in the README).

## Checks

Every pull request runs these checks; run the ones related to your change before pushing:

| Check | Command |
| --- | --- |
| Tests (Pest) | `php artisan test` (or `--filter=<name>`) |
| Code style (Pint) | `vendor/bin/pint` |
| Static analysis (PHPStan) | `vendor/bin/phpstan analyse --memory-limit=1G` |
| Filament (filacheck) | `vendor/bin/filacheck` |
| Translations | `php artisan translations:check --excludedDirectories=vendor` |
| End-to-end tests (Playwright) | `npm run test:e2e` (see [above](#end-to-end-tests)) |

The tests run on MySQL 8 and on SQLite, each with and without a table prefix (`DB_PREFIX`): raw SQL must keep the
prefix, and index names must fit in 64 characters.

## What a change includes

- **Tests**: every change is tested, with Pest feature tests unless a unit test fits better.
- **End-to-end tests**: every change to the interface (a page, field, action, filter, label, navigation item or
  permission) adds, updates or removes the Playwright tests that cover it, in the same change. They read as the steps a
  user follows, covering whole workflows (e.g. a zone, then a territory in it, assigned and returned). Use the helpers
  of `tests/e2e/support/` and unique names (`uniqueName()`), since the tests run in parallel on one database; tests
  that change the whole application (settings, root user) go in `tests/e2e/global-state/`. A bug found by a test and
  not fixed stays visible as a `test.fixme` explaining it, never as a removed or weakened test.
- **Translations**: new texts go in English, Italian and Spanish (`lang/en`, `lang/it`, `lang/es`).
- **User guide**: the pages of `docs/user-documentation/en` and `it`, shown in the app, describe the change; every page
  exists in both languages, and its links and images must resolve (checked by `UserDocumentationTest`).
- **Changelog**: an entry under `## [Unreleased]` in [`CHANGELOG.md`](CHANGELOG.md), in its category (Added, Changed,
  Deprecated, Removed, Fixed or Security). Write it for the users of the app, without repeating the category verb, and
  end it with the pull request:
  `... ([#123](https://github.com/OpenCHelper/OpenCongregationHelper/pull/123)).`

## Releases

1. A `chore: release vX.Y.Z` commit moves the `[Unreleased]` entries under `## [X.Y.Z] - YYYY-MM-DD` and sets the
   `version` of `composer.json` to `vX.Y.Z` (then `composer update --lock` updates the hash of `composer.lock`).
2. Once it is on `main`, pushing the tag `vX.Y.Z` makes the
   [`create-release.yml`](.github/workflows/create-release.yml) workflow publish the release, with that section of the
   changelog as its notes. Versions with a suffix (e.g. `v1.0.0-rc1`) are published as pre-releases.
3. GitHub ignores pushes of more than three tags at once: then run the workflow manually.

## Version numbers

The version headings of the [changelog](CHANGELOG.md) leave out the `v` (e.g. `## [1.0.0] - 2026-12-01`), as
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) writes them and as all the past headings are written: for
[Semantic Versioning](https://semver.org/spec/v2.0.0.html), `v1.0.0` is not itself a version number, the `v` is only a
common prefix for tags, and tools that read changelogs usually expect the bare number. The `v` is kept where it already
is: the git tags (e.g. `v1.0.0`), the titles of the GitHub releases and the `version` of `composer.json`. The release
workflow (`.github/workflows/create-release.yml`) strips the `v` from the tag to find the changelog section of the
release.
