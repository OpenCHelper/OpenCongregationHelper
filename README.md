# Registro Territori

![Laravel](https://img.shields.io/badge/laravel-%23FF2D20.svg?style=for-the-badge&logo=laravel&logoColor=white)
[<img alt="Deployed with FTP Deploy Action" src="https://img.shields.io/badge/Deployed With-FTP DEPLOY ACTION-%3CCOLOR%3E?style=for-the-badge&color=blue">](https://github.com/SamKirkland/FTP-Deploy-Action)
[![Open in Dev Containers](https://img.shields.io/static/v1?label=Dev%20Containers&message=Open&color=blue)](https://vscode.dev/redirect?url=vscode://ms-vscode-remote.remote-containers/cloneInVolume?url=https://github.com/LukeSavefrogs/registro-territori)

## Tech stack

- Laravel 12
- Filament 5
- Livewire 4

## Support

To receive support and/or propose new features please open an issue.

## Changelog

For the full list of changes, please read the [**Changelog**](CHANGELOG.md).

## Alternatives

If Registro Territori does not fit the needs of your congregation, these are valid alternatives:

| Application | Type | Territories |
| --- | --- | --- |
| [Territory Helper](https://territoryhelper.com) | Web app, with a companion mobile app for the publishers | Dedicated to territories: maps, heat maps and assignment statistics, campaigns, S-13 forms, and publishers requesting and returning their territories from their own account |
| [Hourglass](https://www.hourglass-app.com) | Web app, with the *Hourglass Time* mobile app for the publishers | One of the features of a complete congregation management application (field service groups, reports, schedules...) |
| [NW Publisher](https://nwscheduler.com) | Mobile app, companion of the *New World Scheduler* congregation software | Territories are managed in New World Scheduler; publishers see the ones assigned to them, to the congregation or to their field service group, request new ones and mark them as completed |
| [Organized](https://github.com/sws2apps/organized-app) | Open source (MIT) Progressive Web App, for desktop and mobile | Congregation management application (schedules, reports, publisher records...): territory management is under development ([sws2apps/organized-app#5523](https://github.com/sws2apps/organized-app/pull/5523)) |

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

The deploy workflows read the settings of their GitHub environment (`NIGHTLY`, `UAT` or `PREVIEW`, see
[Altervista](#altervista)) and the repository variables. Locally, `act` reads all of them from the files above, under
the same names whatever the environment, so keep one pair of files for each space (both are ignored by git):

```dotenv
# .act/.vars
FTP_SERVER=ftp.{username}.altervista.org
FTP_USERNAME={username}

# Router of the spaces hosting several environments
SPACE_DEFAULT_ENVIRONMENT=test
SPACE_PHP_HANDLER=av-php82
```

```dotenv
# .act/.secrets
FTP_PASSWORD=password

# PREVIEW only: password of the root user of the previews, and API key of cron-job.org
ROOT_PASSWORD=MyVerySecretPassword
CRON_JOB_ORG_API_KEY=MyCronJobOrgApiKey
```

To deploy the preview of a PR, run `act workflow_dispatch` with the same options as above, except for
`--workflows .github/workflows/preview.yml --job deploy --input action=deploy --input pr=<N>`.

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

#### PR previews

Every PR opened from a branch of this repository gets its own preview, like on Vercel or Render: it is deployed to
`https://pr-<N>.<site>.altervista.org` by [`preview.yml`](.github/workflows/preview.yml) on every push, with fresh dummy
data, and removed (files and database tables) when the PR is closed or merged. A comment on the PR shows its status and
URL.

The login page of the previews is pre-filled with the credentials of the root user (`DEMO_PREFILL_LOGIN_CREDENTIALS`),
so that reviewers can test them right away: anyone with the link can log in as root, so the previews MUST hold dummy
data only, and `ROOT_PASSWORD` must not be reused anywhere else. To do the same on TEST, add
`DEMO_PREFILL_LOGIN_CREDENTIALS=true` to its `.env` on the server (`test/.env`); it is always ignored in production.

All the previews share one Altervista space, either a dedicated one or the TEST one (see below): each one lives in
`previews/pr-<N>/` and prefixes its database tables with `p<N>_` (`DB_PREFIX`). Since Altervista only offers FTP, the
release is uploaded as a single archive and unpacked on the server by the
[`ftp-archive-deploy`](#deploying-with-ftp-archive-deploy) action.

Since Altervista has no cron, [cron-job.org](https://cron-job.org) calls the scheduler and the queue endpoints of each
preview (`/api/artisan/schedule/process` and `/api/artisan/queue/process`) every 5 minutes, from 8:00 to 22:55 (Rome
time), Monday to Friday. Their jobs live in a folder of their own, `(Preview) PR-<N>`, managed by the
[`cron-job-org-action`](https://github.com/LukeSavefrogs/cron-job-org-action) action: it creates them on the first
deployment, updates them on every push (each deployment gets a new `ARTISAN_CRON_TOKEN`), and deletes them together
with the preview.

Since the scheduler runs only the tasks due in the minute it is called, every scheduled task (see
[`bootstrap/app.php`](bootstrap/app.php)) must be due inside that window, on a multiple of 5 minutes, or it never runs on
the previews: e.g. `DetectStaleAssignments`, which sends the reminders of the territories out for too long, runs every
Monday at 9:00, Rome time.

The deployments use [GitHub environments](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments)
(Settings → Environments), each with the FTP credentials of its space, under the same names:

| Environment | Deployed by | Variables | Secrets |
| --- | --- | --- | --- |
| `NIGHTLY` | [`upload-nightly.yml`](.github/workflows/upload-nightly.yml) (TEST, on every push to `main`) | `FTP_SERVER`, `FTP_USERNAME` | `FTP_PASSWORD` |
| `UAT` | [`upload-staging.yml`](.github/workflows/upload-staging.yml) (PRE-PRODUCTION, on every release tag) | `FTP_SERVER`, `FTP_USERNAME` | `FTP_PASSWORD` |
| `PREVIEW` | [`preview.yml`](.github/workflows/preview.yml) (the PR previews) | `FTP_SERVER`, `FTP_USERNAME` | `FTP_PASSWORD`, `ROOT_PASSWORD`, `CRON_JOB_ORG_API_KEY` |

`FTP_SERVER` is the FTP hostname of the space (e.g. `ftp.{username}.altervista.org`), also used to build its URLs,
`ROOT_PASSWORD` the password of the root user of the previews (public, since it is pre-filled on their login page), and `CRON_JOB_ORG_API_KEY` an API key of cron-job.org
(Console → Settings → API), better dedicated to the previews. `FTP_SERVER` and `FTP_USERNAME` can be secrets too, but
GitHub then hides every text containing them, like the URLs of the sites (`https://{username}.altervista.org`): the
deployments get no link, and the logs show `***` instead of the hosts. The environments can also restrict who and what deploys
(e.g. `UAT` only from release tags, or after an approval).

Add also these repository variables (Settings → Secrets and variables → Actions → *Variables*), read by the TEST and
the preview deployments, which write the router of their space (see [below](#hosting-the-previews-on-the-test-space)).
They are repository variables, not environment ones, because `NIGHTLY` and `PREVIEW` may deploy to the same space, and
must then write the same router:

| Variable | Value |
| --- | --- |
| `SPACE_DEFAULT_ENVIRONMENT` | Folder served on the site without subdomain: `test` |
| `SPACE_PHP_HANDLER` | PHP version of the space: `av-php82` |

and, optionally:

- the `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` secrets of `PREVIEW`, only if the database is not the one of the
  Altervista account, which is used by default: `my_{username}`, user `{username}` (the FTP username), no password;
- the `ROOT_EMAIL` (default: `root@example.com`) and `MAX_PREVIEWS` (default: `5`) variables of `PREVIEW`.

> [!IMPORTANT]
> Laravel's `db:wipe` (also run by `migrate:fresh`) drops **every** table of the database, whatever its prefix. Here it
> is replaced by [`PrefixAwareWipeCommand`](app/Console/Commands/PrefixAwareWipeCommand.php): with a `DB_PREFIX`, it
> drops only the tables with that prefix, and without one it refuses to run when the database also holds the tables of
> other installations. So every installation sharing a database must have its own prefix.

Limits:

- at most `MAX_PREVIEWS` live previews at the same time, since the 3 GB of the space are shared between files and
  database (a new one fails until a PR is closed);
- PR numbers up to 9999, so that the longest index names stay within the 64 characters allowed by MySQL (the tests
  also run with the longest prefix, `p9999_`, to catch new ones);
- PRs from forks get no preview, since GitHub does not share the secrets with them; neither do the pushes of Dependabot,
  for the same reason: deploy their preview by running the workflow manually with the `deploy` action;
- the previews of PRs that are no longer open (e.g. after a failed removal) are removed every week, or on demand by
  running the workflow manually with the `cleanup` action, together with their cron jobs;
- an API key of cron-job.org can send 100 requests a day: each deployment sends 4 of them (5 the first time, to
  create the folder), and each removal 5, so about 20 pushes a day to the PRs; ask cron-job.org for a higher limit if
  needed.

#### Hosting the previews on the TEST space

The TEST space can host the previews too, without a dedicated space: every environment lives in its own folder, and the
`.htaccess` of the root of the space, written by the [`ftp-archive-deploy`](#deploying-with-ftp-archive-deploy) action,
routes each request by host to the `public/` folder of the environment:

| Host | Folder |
| --- | --- |
| `{username}.altervista.org` (and `www.`) | `test/` (TEST, deployed by [`upload-nightly.yml`](.github/workflows/upload-nightly.yml)), from `SPACE_DEFAULT_ENVIRONMENT` |
| `pr-<N>.{username}.altervista.org` | `previews/pr-<N>/` |
| `<name>.{username}.altervista.org` | `<name>/`: another environment is added by creating a folder |

The folders never overlap, so the only shared resource is the database, where TEST prefixes its tables with `test_`.

Like the previews, TEST is deployed as a single archive: instead of uploading ~20k files one by one through FTP (hours),
the workflow uploads one archive (minutes) with the [`ftp-archive-deploy`](#deploying-with-ftp-archive-deploy) action,
which unpacks it on the server, then swaps the old and the new release at once. The `.env` file (managed on the server), `storage/app` and `storage/logs` are moved into each new
release.

The deployer never runs longer than the PHP execution time limit of the server: each request uses at most a third of it
(and at most 15 seconds), the long tasks (unpacking, deleting a release) are split over as many requests as needed, and
a failed request is retried with a smaller budget, since every step can be safely repeated.

To host the previews on the TEST space, give the `PREVIEW` environment the same FTP secrets as `NIGHTLY`: the previews
then use the database of the TEST account. `MAX_PREVIEWS` can be lowered if the 3 GB, now shared with
TEST, run short.

The first time, **before** TEST is deployed with this layout (that is, before merging it into `main`):

1. move the `.env` file of TEST from the root of the space to `test/.env`, and set `DB_PREFIX='test_'` in it (leave
   `SESSION_DOMAIN` unset, so that the cookies of TEST are not sent to the previews);
2. after the first deployment into `test/`, delete the old files of the application from the root of the space
   (including the `.ftp-deploy-sync-state__*.json` files), keeping `test/`, `previews/`, `_deployer/` and `.htaccess`;
3. drop the old tables without prefix from the database (e.g. with phpMyAdmin: they only hold dummy data).

If the prefix is forgotten while previews exist, the deployment of TEST fails (`migrate:fresh` refuses to wipe a
database shared with other installations) instead of dropping the tables of the previews.

#### Deploying with `ftp-archive-deploy`

[`ftp-archive-deploy`](.github/actions/ftp-archive-deploy/action.yml) is the action that deploys TEST and the PR
previews, and any other environment of a space reachable only through FTP:

```yaml
- uses: ./.github/actions/ftp-archive-deploy
  with:
    ftp-server: ${{ secrets.FTP_SERVER }}     # secrets of the GitHub environment of the job
    ftp-username: ${{ secrets.FTP_USERNAME }}
    ftp-password: ${{ secrets.FTP_PASSWORD }}
    url: https://{username}.altervista.org   # root of the space
    target: test                             # `<folder>`, or `pr-<N>` for `previews/pr-<N>`
    keep: |                                  # paths of the live release moved into the new one (optional)
      .env
      storage/app
    reuse-vendor: true                       # reuse the Composer packages when unchanged (optional)
    exclude-file: .git-ftp-ignore            # files never deployed (optional, this is the default)
    manage-router: true                      # write the router of the space (optional), with:
    default-environment: ${{ vars.SPACE_DEFAULT_ENVIRONMENT }}
    php-handler: ${{ vars.SPACE_PHP_HANDLER }}
```

It uploads the release as a single archive, unpacks it on the server with the PHP deployer it bundles, then swaps the old
and the new release at once. The deployer installs itself in `_deployer/<version>/` of the space (a hash of its files),
so runs with different versions of the action never get in each other's way. Its time budget, and how long the
deployment took, are available as outputs.

With `reuse-vendor`, a release whose Composer packages did not change (same `vendor/composer/installed.json` as the
live release) uploads only `vendor/autoload.php` and `vendor/composer/` (the autoloader, which also maps the classes of
the application): the packages are moved from the live release instead. That is most of the files of the archive, so
most deployments upload and unpack about 2,000 files instead of about 27,000.

The files that only serve to develop, build or test the application (the tests, the development tools and their
settings, the front-end sources, the tests and documentation of the Composer packages...) are never deployed: they are
listed in [`.git-ftp-ignore`](.git-ftp-ignore), in git-ftp's format (one shell glob per line, matched against the whole
path, where `*` also matches `/`). The archive deployments read it through the `exclude-file` input, and the file sync
of PRE-PRODUCTION through [`git_ftp_ignore_globs.sh`](scripts/git_ftp_ignore_globs.sh). `public/storage` is never
deployed either: every deployment creates the link on the server with `artisan storage:link`. Before that, TEST and
PRE-PRODUCTION remove a `public/storage` folder left by the old file sync (checked over FTP, and only when it holds
nothing but the `.gitignore` of the build's copy), since `storage:link` replaces a link, not a folder.

With `manage-router`, the action also writes the router of the space (the `.htaccess` of its root and of `previews/`,
from the templates of its `router/` folder), sending each host straight to the document root of its environment
(`public/` by default, `document-root` input). The router is shared by every workflow deploying to the space, so they
must all pass the same settings: that is why they come from repository variables.

