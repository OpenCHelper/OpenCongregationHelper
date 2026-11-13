# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- PR previews: every PR gets its own preview at `https://pr-<N>.<site>.altervista.org`, redeployed with fresh dummy
  data on every push and removed when the PR is closed or merged. All the previews share one Altervista space (a
  dedicated one, or the TEST one) and its database, each one prefixing its tables with `p<N>_` (see the README). A
  shared `robots.txt` keeps them out of the search engines.
- Altervista spaces hosting several environments: each one lives in its own folder (`test/`, `previews/`, ...), and the
  `.htaccess` of the root routes the requests by host (`<site>` to `test/`, `pr-<N>.<site>` to the previews,
  `<name>.<site>` to `<name>/`).
- TEST and preview deployments reuse the Composer packages of the live release when they did not change
  (`reuse-vendor` input of the `ftp-archive-deploy` action): they upload and unpack about 2,000 files instead of about
  27,000 ([#157]).
- Dashboard map coloring modes, chosen from a menu above the map and remembered for the session: by **zone**, by
  **current status** (available or not), by **assignment frequency** in the last 12 months (low, medium or frequent)
  and by **campaign status** (out for another assignment, to be assigned, in progress or worked) for the chosen
  campaign that is not terminated. A legend under the map lists the colors with the number of territories of each
  one, and clicking a territory shows its zone, its status and a link to its page (closes [#34]).

### Changed

- **The Artisan endpoints moved from the `artisan.` subdomain to the `/api/artisan` path** of the application (e.g.
  `https://example.com/api/artisan/queue/process`): update the URLs called by the external cron services.
- `db:wipe` (also run by `migrate:fresh`) only drops the tables of the installation: those starting with its table
  prefix (`DB_PREFIX`). Without a prefix, it refuses to run when the database also holds the tables of other
  installations, instead of dropping them too.
- **TEST is deployed into the `test/` folder of its space**, without the development dependencies, and its tables are
  prefixed with `test_`: before merging, move its `.env` file into `test/` and set `DB_PREFIX='test_'` (see the
  README).
- TEST is deployed as a single archive, unpacked on the server and swapped with the live release at once (minutes
  instead of hours, and no more mix of old and new files while uploading), keeping `.env`, `storage/app` and
  `storage/logs`. The unpacking is split in requests that stay within the PHP execution time limit of the server, and
  retried when they fail.
- The deployments use GitHub environments (`NIGHTLY` for TEST, `UAT` for PRE-PRODUCTION, `PREVIEW` for the PR previews),
  whose secrets share the same names (`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`): **before merging, create them**
  with the values of the old `FTP_*_TEST` and `FTP_*_QA` repository secrets.
- The archive deployments are packaged as the `ftp-archive-deploy` action (`.github/actions/`), used by TEST and the PR
  previews: it bundles its PHP deployer, installed in `_deployer/<version>/` of the space, and writes the router of the
  spaces hosting several environments (from the `SPACE_DEFAULT_ENVIRONMENT` and `SPACE_PHP_HANDLER` repository
  variables), sending each host straight to the `public/` folder of its environment.
- `fakerphp/faker` is a production dependency, so that the PR previews and TEST can be installed without the
  development dependencies and still seed dummy data.
- The deployments (TEST, PR previews and PRE-PRODUCTION) leave out the files that only serve to develop, build or test
  the application, listed in `.git-ftp-ignore` (git-ftp's format), among them the tests and documentation of the
  Composer packages ([#157]).
- Upgraded to Filament 5 and Livewire 4, together with the Filament plugins (among them the knowledge base, now at
  version 3, and the activity log, now at version 4).
- The **Logs** page (Developer group, super administrators only) now uses `achyutn/filament-log-viewer`, since
  `filipfonal/filament-log-manager` does not support Filament 5. Log entries are listed in a table with level tabs,
  a date filter and stack traces, and log files can still be cleared, but no longer downloaded.

### Fixed

- The result of a territory import (the body of its notification and the reasons of the failed rows, also in the
  downloadable CSV) being in English, and the rest of it in the default language of the application instead of the
  one of the user who started the import. All the importer texts are now translated, column labels included, and
  rendered in the preferred language of that user ([#165]).
- Dashboard failing to load when a zone with territories on the map has no color ([#34]).
- Clicking the dashboard map showing debug notifications and zooming the map back out to all the territories ([#34]).
- Zone names shown as HTML in the layers menu of the dashboard map ([#34]).
- `public/storage` is now a link on the servers, created by `artisan storage:link` after every deployment: the
  deployments used to upload the build's link as a plain folder, so the public files of `storage/app/public` were not
  served. TEST and PRE-PRODUCTION first remove that folder when it holds nothing but the copy's `.gitignore`
  ([#157]).
- Sorting territories by assignment duration, and grouping them by campaign, failing when the tables are prefixed
  (`DB_PREFIX`).
- Assignments of a terminated campaign failing to save when edited, for example to return the territory, with "The
  selected campaign is invalid" and "Cannot assign territories to a terminated campaign". A terminated campaign is
  now refused only when an assignment is linked to it, and this is enforced for every assignment saved, not only
  those saved from the forms ([#104]).
- Every page failing with "hash_equals(): Argument #1 ($known_string) must be of type string, null given" for the
  visitors holding a "remember me" cookie that is no longer valid (for example after logging out from another
  device), a regression of Laravel 12.69.0 fixed by updating it to 12.69.3. The cookie is ignored again.
- Tags duplicated when saving a territory or a zone with the interface not in English: the Tags page showed the same
  tag twice, and the tag filters did not find it ([#166]).

## [2.0.0-alpha6] - 2026-11-11

### Added

- Welcome page describing the project scope and its current features, with links to the GitHub repository, the user
  guide and the login page, and the application version. Available in English, Italian and Spanish ([#142]).
- `ENABLE_SHARED_PII` environment variable to show personal data (assignee name and territory notes) on publicly
  shared assignment pages, where it is now hidden by default ([#141]).
- User guide covering every feature of the application, in English and Italian, with screenshots and step-by-step
  guides: basics (login, interface, dashboard, profile, notifications), territories (list, creation, map drawing,
  import, zones), assignments (assigning and returning, history, sharing, campaigns), administration (users, roles
  and permissions, tags) and walkthroughs for the first setup, campaigns and publishers. The setup guide now
  covers the installation, all the main settings and the scheduled tasks. Tests check that every page renders and
  that its links and images exist.

### Changed

- Bumped the application version in `composer.json` to `v2.0.0-alpha5`; it was still set to `v2.0.0-alpha3`.
- Notification emails are queued and sent when the queue is processed, instead of during the request. On hosts without
  a queue worker this requires the cron calling the queue endpoint (`/queue/process`) ([#146]).
- Users who cannot log in are no longer notified when a campaign is created ([#146]).
- Assignees are sorted by name when assigning territories ([#146]).
- The database seeder reads the initial root user through the configuration and no longer depends on development
  packages ([#146]).

### Removed

- Unused API controller methods, form requests and territory assignment bulk actions ([#146]).
- Faster CI: the test workflow caches the Sail Docker image layers, rebuilding the image from scratch every 7 days,
  and builds the frontend assets on the runner with cached npm dependencies. The linter workflow no longer starts the
  Sail containers ([#145]).
- Updated the `actions/cache` and `actions/checkout` GitHub Actions to their latest major versions, since the previous
  ones ran on a deprecated Node.js runtime ([#145]).

### Fixed

- Home page returning a server error when frontend assets are built, because `resources/css/guest.css` was missing
  from the Vite build ([#142]).
- Publicly shared assignment pages returning a server error when frontend assets are built, because the guest panel
  theme (`resources/css/filament/guest/theme.css`) was missing from the Vite build ([#141]).
- Error notifications of the assign/return territory actions never being shown ([#141]).
- Users allowed to list all assignments only seeing their own, because of an outdated permission name. The Shield
  seeder now uses the current permission names ([#141]).
- Already assigned territories being offered when adding an assignment to a campaign ([#141]).
- Campaign filters returning wrong results when combined with other conditions ([#141]).
- Campaign overview widget failing when there are no territories, exceeding 100% when a territory was assigned more
  than once, and always showing the remaining-time warning ([#141]).
- Stale assignment emails mentioning the wrong territory ([#141]).
- Territory import rejecting every territory of an existing zone as a duplicate, and "update existing" ignoring the
  zone ([#141]).
- Editing a user requiring a new password to be entered ([#141]).
- Emails sent through the native `mail()` transport having duplicated `To`/`Subject` headers and no MIME content
  type ([#141]).
- Intermittent CI failures caused by tests starting before MySQL accepted connections ([#141]).
- Intermittent CI failures caused by commands running in the Sail container before its user ID was updated to match
  the runner's ([#145]).
- Fake data seeder (`FakeDataSeeder`) failing randomly when all the fake tags were created with the same type. Zone
  and territory factories now create a tag of the needed type when none exists ([#144], closes [#123]).
- Territories having more than one assignment in progress: assigning, reopening or restoring an assignment is now
  refused when the territory is already assigned ([#146]).
- Sorting territories by assignment duration failing on databases other than MySQL ([#146]).
- Links in emails sent through the queue and scheduler endpoints pointing to the `artisan.` subdomain instead of the
  application URL ([#146]).
- Territories table running one query per territory for the assignment duration, and loading all the territories of
  each zone to count them in the group titles ([#146]).
- Stale assignment detection loading every assignment in progress in memory ([#146]).
- Database seeding failing with a cached configuration or without development dependencies installed ([#146]).
- Log files growing quickly because of debug logs written on every render of the territory form ([#146]).
- Altervista deploys skipping the post-deploy Artisan commands (migrations and cache optimisations), because the
  token mask was written into the command output ([#147]).
- Deleting a user leaving their team memberships behind ([#148]).
- Static analysis (PHPStan) errors in the contacts section of the profile page, caused by an unused form that the
  "add contact" actions also used to save relationships instead of their own form ([#149]).
- Saving a territory erasing its map, even when only other fields were changed ([#150]).
- Creating a territory failing, and an image uploaded while creating it not being linked to the territory ([#150]).
- Assignments in progress failing to save when edited, with "The selected territory is invalid" ([#150]).
- Global search results being hidden under the top bar on tablets and desktops ([#150]).
- Campaign status showing "Almost Expired" for every campaign with an end date in the future, and never "Expiring".
  The status now depends on the days left until the end date: "Expiring" with 7 days or less, "Almost Expired" with
  3 days or less ([#151]).
- Territory map staying a grey box, without tiles nor polygon, after switching from the image tab to the map tab,
  until the whole map was scrolled into view. A map below the bottom of the screen when the page loaded (e.g. on
  phones) had the same problem. The map now shows as soon as part of it is visible.

### Security

- **BREAKING**: The queue and scheduler endpoints used by external cron services (`/queue/process` and
  `/schedule/process`) required no authentication. They now require an `X-Cron-Token` header matching the new
  `ARTISAN_CRON_TOKEN` environment variable, and are rate limited ([#141]).
- Only super administrators can impersonate users, and the `root` user can no longer be impersonated ([#141]).
- Only super administrators can assign roles, so users can no longer grant themselves the `super_admin` role
  ([#141]).
- The API tokens page is only available when the API feature is enabled, the API endpoints now check the user's
  permissions and the territories and zones API is read-only ([#141]).
- The token protecting the remote Artisan endpoints is compared in constant time, an empty token is rejected and the
  submitted token is no longer logged ([#141]).
- Email bodies, including password reset links, are no longer written to the logs ([#141]).
- Assign/return territory actions now check the user's permissions ([#141]).
- The database password is no longer written to the logs when running the migrations ([#146]).
- Territory import only downloads images from public hosts (also after redirects), with timeouts and a 10 MB size
  limit, and deletes invalid downloads ([#146]).
- The deploy workflow masks the temporary Artisan token in the GitHub Actions logs and always removes it from the
  server, even when a command fails ([#146]).
- The root `.htaccess` denies all requests when `mod_rewrite` is not available, instead of serving the project files
  (e.g. `.env`) ([#146]).
- The assignments shown on the territory, campaign and user pages are filtered like the assignments list, so users
  who cannot list every assignment only see their own, without relying on the page permissions alone ([#148]).

## [2.0.0-alpha5] - 2026-09-16

### Added

- Import territories from a CSV file. The import supports name, description, zone, notes, commercial flag, GeoJSON
  polygon and image URL. Images are downloaded into the application storage, only `http`/`https` URLs pointing to an
  `image/*` MIME type are accepted, and rows duplicating an existing territory in the same zone are rejected.
- Upload a territory image from a URL, as an alternative to uploading a file.
- In-app user documentation (knowledge base) at `/knowledge-base`, available in English and Italian and readable
  without logging in.
- UI switcher that lets each user customize font, accent color, layout, font size and density.
- Bottom navigation bar on mobile devices.
- Highlighted (solid) navigation icon for the active page.
- Emergency contacts (phone number and/or address) for users, editable by each user from the profile page and by
  administrators from the "Users" menu ([#116]).
- Campaign details page with an overview widget, an assignments chart and a page to manage and bulk-terminate the
  campaign assignments. The territories table can now be grouped by campaign assignment ([#120]).
- Date and time of the last commit next to the version in the sidebar ([#135]).
- Protection of the `root` user on non-production environments: its email, password and roles can no longer be
  changed by anyone, including the `root` user itself ([#122]).
- `.php-version` file as the single source of truth for the PHP version used by CI and tooling ([#134]).
- Dev Container configuration for local development.
- Static analysis with Larastan and FilaCheck.
- Laravel Boost guidelines and skills for AI-assisted development ([#132]).
- GitHub Copilot instructions and setup steps ([#110], [#111], [#136], [#138]).

### Changed

- **BREAKING**: Upgraded to Laravel 12 and Filament 4 ([#139]). Filament resources now follow the Filament 4
  directory structure (e.g. `app/Filament/Resources/Territories/TerritoryResource.php`).
- The dashboard global map now uses `eduardoribeirodev/filament-leaflet` instead of `webbingbrasil/filament-maps`
  ([#139]).
- **BREAKING**: Territory names must be unique within their zone.
- Reorganized the sidebar navigation: groups have been reordered, roles are now in the same group as users and
  assignments are listed last in their group.
- The local development environment (Laravel Sail) now uses Node.js 24, to work around a regression in Node.js
  22.22.2, and provides default values for `WWWUSER` and `WWWGROUP`.
- Deployment workflows to Altervista have been merged into a single reusable GitHub Actions workflow, with remote
  Artisan execution extracted into a dedicated action.
- Updated dependencies.

### Removed

- Artisan commands panel (`tomatophp/filament-artisan`), not compatible with Filament 4 ([#139]).
- `stephenjude/filament-jetstream` dependency, not compatible with Filament 4 ([#139]).

### Fixed

- Stats overview widget failing on SQLite databases ([#137]).
- `root` user not receiving the `super_admin` role.
- Wrong `Auth` facade import in the activity log and mail log resources.
- Application version overflowing out of the sidebar.
- Creating a territory overwriting its image path.
- Territory form tabs hidden outside of the view page, and image tab not selected when no map is available.
- Missing styles on guest pages and broken UI styles.
- Documentation panel not being reachable.
- Wrong values in the example environment file ([#107]).

### Security

- Updated Livewire to 3.6.4 ([#106]), fixing a remote code execution vulnerability (CVE-2025-54068).

## [2.0.0-alpha4] - 2025-06-23

### Fixed

- Map edit controls shown on read-only territory assignment views ([#103]).

## [2.0.0-alpha3] - 2025-06-22

### Added

- Git branch and commit shown in the sidebar ([#96]). On systems without `git`, they can be read from the files
  pointed to by the new `APP_VERSION_BRANCH_FILE` (defaults to `.git/HEAD`) and `APP_VERSION_COMMIT_FILE` (defaults to
  `.git/refs/heads/{branch}`) environment variables.

### Changed

- **BREAKING**: Renamed `TerritoryAssignmentType` to `Campaign` ([#99], closes [#98]).
- Campaigns are now optional when assigning a territory, and no campaign is selected by default ([#100]).
- Campaign validity dates are now inclusive ([#100]).
- The creator of a campaign is now always recorded ([#100]).
- The territory map in the assignment details is shown only when the territory has one.

### Fixed

- Territories could be assigned to terminated campaigns ([#100]).
- Sidebar not starting closed on mobile devices ([#94]).
- "User profile" page not reachable by users without a verified email ([#101]).
- Raw GeoJSON shown among the territory assignment details.
- Territory notes hidden from the assignment details when empty.

## [2.0.0-alpha2] - 2025-06-17

### Added

- Support for multiple languages, with English, Italian and Spanish translations ([#78]).
- Tag manager to create and organize tags for territories and zones, with per-type tabs, usage counters and
  translations ([#79]).
- Territory map in the territory assignment details ([#77]).
- Share the details of a territory assignment through a public link, viewable without logging in ([#80]).
- Current and past status column in the territories table, and tabs to filter commercial territories ([#93]).

### Fixed

- Sorting territories by assignee ([#93]).

## [2.0.0-alpha1] - 2025-05-17

Complete rewrite of the application on top of Laravel 11, Filament 3 and Laravel Jetstream ([#35]).

### Added

- Administration panel at `/app`, with login through Laravel Jetstream and Fortify.
- Territories management: creation, editing and deletion, image, notes, commercial flag, filters and a read-only view
  page.
- Territory polygon drawing and editing on an OpenStreetMap map, with selectable tile layers.
- Territory zones with a custom color and a list of their territories. Zones can also be created directly from the
  territory form.
- Territory assignments: assign and return territories, one at a time or in bulk, from both the territory page and
  the territories table, with full assignment history and a details page.
- Campaigns (named "assignment types" at the time) with validity dates, status badge, status filter and sorting.
- Dashboard with a global map of all territories, colored by zone or by assignment status, remembering the selected
  tile layer.
- Dashboard overview widget with the total number of territories and the average assignment duration over the last
  12 months.
- Users management, with first and last name, read-only view page and the registry of territories assigned to each
  user.
- Virtual users, which can be assigned territories but cannot log in.
- Roles and permissions management through Filament Shield, with policies for all resources (closes [#45]). Users
  allowed to view only their own assignments see just those.
- Impersonation of other users.
- Tags for territories and zones.
- Global search across territories, zones and campaigns, matching name, description, zone, notes and tags.
- Activity log of user actions ([#71]).
- In-app notifications when an assignment starts or ends ([#53]), when a campaign is created, and when an assignment
  has been out for more than 60, 90, 120 or 150 days (closes [#56]).
- Language switcher (Italian and English).
- Warning when leaving a page with unsaved changes.
- Live indicator of other users viewing or editing the same territory or zone (`discoverydesign/filament-gaze`).
- Application logs and sent emails viewers.
- Environment indicator on non-production environments.
- Application version in the sidebar.
- Mail transport based on PHP's native `mail()` function, used as the primary mailer with failover.
- Token-protected HTTP API to run Artisan commands, the queue worker and scheduled jobs on hosts without shell access
  (closes [#57]), and an Artisan commands panel on test environments.
- Configurable database table prefix (`DB_PREFIX`) and engine (`DB_ENGINE`).
- Local development environment based on Laravel Sail, with Mailpit, database seeders and factories.
- Automated deployment to the Altervista staging and nightly environments through GitHub Actions, including database
  migrations and optimizations on the remote server.

### Changed

- **BREAKING**: Rewrote the database structure from scratch. It is not compatible with the v1 database.
- Renamed the `testing` environment to `nightly` ([#69]).

### Removed

- **BREAKING**: Legacy v1 source code, including the third-party plugins previously committed to the repository.
- User self-registration: new users can only be created by an administrator.
- Teams and two-factor authentication.

### Security

- HTTPS is enforced everywhere except in local development.
- The API can only be used by authenticated users.
- Uploaded territory images are stored on a private disk ([#69], closes [#70]).

[#34]: https://github.com/LukeSavefrogs/registro-territori/issues/34
[#35]: https://github.com/LukeSavefrogs/registro-territori/pull/35
[#45]: https://github.com/LukeSavefrogs/registro-territori/issues/45
[#53]: https://github.com/LukeSavefrogs/registro-territori/issues/53
[#56]: https://github.com/LukeSavefrogs/registro-territori/issues/56
[#57]: https://github.com/LukeSavefrogs/registro-territori/issues/57
[#69]: https://github.com/LukeSavefrogs/registro-territori/pull/69
[#70]: https://github.com/LukeSavefrogs/registro-territori/issues/70
[#71]: https://github.com/LukeSavefrogs/registro-territori/pull/71
[#77]: https://github.com/LukeSavefrogs/registro-territori/pull/77
[#78]: https://github.com/LukeSavefrogs/registro-territori/pull/78
[#79]: https://github.com/LukeSavefrogs/registro-territori/pull/79
[#80]: https://github.com/LukeSavefrogs/registro-territori/pull/80
[#93]: https://github.com/LukeSavefrogs/registro-territori/pull/93
[#94]: https://github.com/LukeSavefrogs/registro-territori/pull/94
[#96]: https://github.com/LukeSavefrogs/registro-territori/pull/96
[#98]: https://github.com/LukeSavefrogs/registro-territori/issues/98
[#99]: https://github.com/LukeSavefrogs/registro-territori/pull/99
[#100]: https://github.com/LukeSavefrogs/registro-territori/pull/100
[#101]: https://github.com/LukeSavefrogs/registro-territori/pull/101
[#103]: https://github.com/LukeSavefrogs/registro-territori/pull/103
[#104]: https://github.com/LukeSavefrogs/registro-territori/issues/104
[#106]: https://github.com/LukeSavefrogs/registro-territori/pull/106
[#107]: https://github.com/LukeSavefrogs/registro-territori/pull/107
[#110]: https://github.com/LukeSavefrogs/registro-territori/pull/110
[#111]: https://github.com/LukeSavefrogs/registro-territori/pull/111
[#116]: https://github.com/LukeSavefrogs/registro-territori/pull/116
[#120]: https://github.com/LukeSavefrogs/registro-territori/pull/120
[#122]: https://github.com/LukeSavefrogs/registro-territori/pull/122
[#123]: https://github.com/LukeSavefrogs/registro-territori/issues/123
[#132]: https://github.com/LukeSavefrogs/registro-territori/pull/132
[#134]: https://github.com/LukeSavefrogs/registro-territori/pull/134
[#135]: https://github.com/LukeSavefrogs/registro-territori/pull/135
[#136]: https://github.com/LukeSavefrogs/registro-territori/pull/136
[#137]: https://github.com/LukeSavefrogs/registro-territori/pull/137
[#138]: https://github.com/LukeSavefrogs/registro-territori/pull/138
[#139]: https://github.com/LukeSavefrogs/registro-territori/pull/139
[#141]: https://github.com/LukeSavefrogs/registro-territori/pull/141
[#142]: https://github.com/LukeSavefrogs/registro-territori/pull/142
[#144]: https://github.com/LukeSavefrogs/registro-territori/pull/144
[#145]: https://github.com/LukeSavefrogs/registro-territori/pull/145
[#146]: https://github.com/LukeSavefrogs/registro-territori/pull/146
[#147]: https://github.com/LukeSavefrogs/registro-territori/pull/147
[#148]: https://github.com/LukeSavefrogs/registro-territori/pull/148
[#149]: https://github.com/LukeSavefrogs/registro-territori/pull/149
[#150]: https://github.com/LukeSavefrogs/registro-territori/pull/150
[#151]: https://github.com/LukeSavefrogs/registro-territori/pull/151
[#157]: https://github.com/LukeSavefrogs/registro-territori/pull/157
[#165]: https://github.com/LukeSavefrogs/registro-territori/pull/165
[#166]: https://github.com/LukeSavefrogs/registro-territori/pull/166
