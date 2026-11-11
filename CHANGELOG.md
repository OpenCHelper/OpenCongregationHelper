# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Welcome page describing the project scope and its current features, with links to the GitHub repository, the user
  guide and the login page, and the application version. Available in English, Italian and Spanish ([#142]).
- `ENABLE_SHARED_PII` environment variable to show personal data (assignee name and territory notes) on publicly
  shared assignment pages, where it is now hidden by default ([#141]).

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
