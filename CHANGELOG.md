# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0-alpha12] - 2026-11-22

### Added

- **Report a bug** button in the **About** modal: it opens the bug report form on GitHub prefilled with the address
  and environment of the installation, the versions of the app, PHP, Laravel and Filament and the browser
  ([#61](https://github.com/OpenCHelper/OpenCongregationHelper/pull/61)).
- **Territory coverage** on the dashboard: the percentage of territories worked at least once in the last 6, 12 or
  24 months (also excluding campaigns) and the 5 territories never worked or not worked for the longest time; the
  periods can be changed from the Settings pages ([#51](https://github.com/OpenCHelper/OpenCongregationHelper/pull/51)).
- **Settings pages**: super administrators can change the default map centre and the personal data on shared pages
  from **Administration → Settings**, without editing `.env`, which now only holds their defaults
  ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).
- Logo and default primary colour of the application, from the Settings pages
  ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).
- Setting to turn the sharing of assignments off: no link can then be created and the existing ones are deleted
  ([#58](https://github.com/OpenCHelper/OpenCongregationHelper/pull/58)).
- Address of the Kingdom Hall, on the General settings page
  ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).
- Days after which assignments are out for too long and the day and time of the weekly reminders, from the Settings
  pages ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).
- Days before their end from which campaigns are expiring and almost expired, from the Settings pages
  ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).
- Showing the personal data on shared pages is a clear choice between hiding them (recommended) and showing them,
  which must be confirmed after reading what it means
  ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).
- Months and number of assignments that set the assignment frequency on the dashboard map, from the Settings pages
  ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).
- The installed app takes the primary colour chosen on the Settings pages
  ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).
- Weekly summary, by email and in the app, to the members of the territory department listing the territories out
  for too long, with their assignees, so that they can follow up; it can be limited to the overseers from the
  Settings pages ([#54](https://github.com/OpenCHelper/OpenCongregationHelper/pull/54)).
- **Return requests**: publishers can ask the territory department to close their assignment with one click, and
  its members are notified; also from shared links, when enabled from the Settings pages (default from
  `GUEST_ALLOW_RETURNING_ASSIGNMENT`) ([#56](https://github.com/OpenCHelper/OpenCongregationHelper/pull/56)).
- **Publisher statistics** on the publisher's page: the zones and territories they take most often
  ([#55](https://github.com/OpenCHelper/OpenCongregationHelper/pull/55)).
- **User invitations**: users created with access, and virtual users without a password who are given access, receive
  an email with a link to choose their password (valid for 6 hours), which also confirms their email address. It is
  sent again to the new address when the email of a user who has not chosen their password yet is corrected, and
  "Resend invitation" sends a new one. Administrators no longer set passwords
  ([#52](https://github.com/OpenCHelper/OpenCongregationHelper/pull/52)).
- **Two-factor authentication** with an authenticator app, which every user can enable from their profile
  ([#60](https://github.com/OpenCHelper/OpenCongregationHelper/pull/60)).
- **Security settings**: super administrators can require every user to set up two-factor authentication and to
  confirm their email address before using the app; both are off by default and can be turned on only once the root
  user meets them ([#60](https://github.com/OpenCHelper/OpenCongregationHelper/pull/60)).

### Changed

- **UX improvements** for smaller and scaled screens: the sidebar can be collapsed to icons only on desktop too,
  from the arrow next to the app name; shorter descriptions on the departments and publications lists, and shorter
  help texts under the "Offered" switch of publications and the role of department members.
- The weekly reminders for the territories out for too long follow the timezone of the application (`APP_TIMEZONE`)
  instead of always Rome time: with `APP_TIMEZONE=UTC` they now arrive one or two hours later in Italy, unless the
  time is changed from the Settings pages. The publications received regularly are requested again at 9:00 in that
  timezone too ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).

### Fixed

- The windows to add a new item in Italian are now titled "Crea …" instead of "Salva …" (e.g. "Crea Poster").

### Security

- The shared pages ask search engines not to index them (`X-Robots-Tag` header and `robots` meta tag), so that they
  don't show up in search results even when their links are posted somewhere public
  ([#43](https://github.com/OpenCHelper/OpenCongregationHelper/pull/43)).
- Administrators can no longer set or change the password of a user: users choose their own, from the invitation,
  with "Forgot password?" or from their profile ([#52](https://github.com/OpenCHelper/OpenCongregationHelper/pull/52)).

## [1.0.0-alpha11] - 2026-11-20

### Added

- **Departments** (Administration → Departments): overseers and assistants who manage the sections of their
  department without needing a role, starting with the Territory Department; departments column and filter in the
  users list ([#45](https://github.com/OpenCHelper/OpenCongregationHelper/pull/45)).
- **Posters inventory** (Public Witnessing → Posters): the magnetic posters of the displays, with their copies in
  each storage place, titles in every language and import and export from a spreadsheet
  ([#46](https://github.com/OpenCHelper/OpenCongregationHelper/pull/46)).
- **Publications**: the publication list, who receives publications on paper regularly and the requests of each
  publisher, from requested to ordered and delivered, made again automatically every period
  ([#47](https://github.com/OpenCHelper/OpenCongregationHelper/pull/47)).

### Fixed

- The password reset and email verification emails are now in the language of the recipient, in Italian and
  Spanish too, instead of always in English ([#42](https://github.com/OpenCHelper/OpenCongregationHelper/pull/42)).

### Security

- Territory images can no longer be SVG files, which can carry scripts: upload a PNG, JPEG or WebP picture instead
  ([#46](https://github.com/OpenCHelper/OpenCongregationHelper/pull/46)).

## [1.0.0-alpha10] - 2026-11-18

### Added

- **Installable app**: add it to the home screen of smartphones or install it on computers, with its own icon and
  window, and a page explaining when there is no connection
  ([#7](https://github.com/OpenCHelper/OpenCongregationHelper/pull/7)).
- Offer to install the app after logging in: an **Install** button on Android, Chrome and Edge, and the steps to add it
  to the home screen on iPhone and iPad ([#12](https://github.com/OpenCHelper/OpenCongregationHelper/pull/12)).
- **Push notifications**: receive the notifications on your phone or computer even with the app closed, after enabling
  them on each device from your profile. Administrators must first generate the keys with `php artisan webpush:vapid`
  ([#41](https://github.com/OpenCHelper/OpenCongregationHelper/pull/41)).
- **Average coverage time** on the territory page: how long the territory takes to be covered on average, based on its
  returned assignments ([#19](https://github.com/OpenCHelper/OpenCongregationHelper/issues/19)).

### Changed

- The version information moved from the bottom of the sidebar to **About** in the profile menu. The branch, commit
  and date of the build are no longer shown in production
  ([#13](https://github.com/OpenCHelper/OpenCongregationHelper/pull/13)).

### Fixed

- Top bar scrolling sideways on smartphones: the help button is now more compact and shows only its icon there
  ([#9](https://github.com/OpenCHelper/OpenCongregationHelper/pull/9)).
- Profile photos shown as broken images (403) on hostings that cannot serve the `public/storage` link, such as
  Altervista: the application now serves the files of the public disk itself. The temporary links to the private
  files (e.g. the images of the territories) move from `/storage/` to `/private-storage/`: links created before the
  update stop working, reload the page to get new ones
  ([#10](https://github.com/OpenCHelper/OpenCongregationHelper/pull/10)).

## [1.0.0-alpha9] - 2026-11-17

### Added

- The map of a new territory opens on the territories already drawn: those of its zone when created from the zone's
  territories page, otherwise all of them.
- `MAP_DEFAULT_CENTER` setting (`"latitude,longitude"`), where the maps open while no territory has a map yet.
- Assignments still in progress whose campaign has ended (red) or ends within a week (amber) stand out in the tables of
  assignments, with an accent border and a warning under the campaign name.
- "Campaign ended" and "Campaign ending soon" tabs on the assignments page, and a banner listing the territories still
  out for an ended campaign, to show them or return them all at once.
- Banner on the page of an assignment whose campaign has ended (or is about to end), reminding to return the territory.
- Red badge next to "Assignments" in the menu with the number of assignments still in progress of an ended campaign.
- Amber badge next to "Campaigns" in the menu with the number of campaigns ending within a week.

### Changed

- Maps with nothing to show yet open on Warwick, New York (USA) instead of Rome, unless `MAP_DEFAULT_CENTER` is set.
- On demo installations with the pre-filled login form, the email field is no longer focused, so the login button can
  be clicked right away.

## [1.0.0-alpha8] - 2026-11-16

### Added

- GitHub release published automatically for every version tag, with the notes of its section of this changelog.
- Purple environment badge in the top bar for demo installations (`APP_ENV=demo`), like the PR previews.
- [Public demo](https://ochdemo.altervista.org), with the latest release and dummy data, linked from the README and
  the user guide.
- Login page of the PR previews pre-filled with the root user's credentials, so reviewers can test right away; the
  previews are public demos, so save only dummy data there. Other demo installations (e.g. TEST) can enable it with the
  new `DEMO_PREFILL_LOGIN_CREDENTIALS` setting, always ignored in production.

### Changed

- Versions renumbered for the first release as OpenCongregationHelper: `2.0.0-alpha1` to `2.0.0-alpha7` are now
  `1.0.0-alpha1` to `1.0.0-alpha7`.
- **BREAKING**: The project is now called OpenCongregationHelper and lives at
  `https://github.com/OpenCHelper/OpenCongregationHelper`. Installations without an `APP_NAME` setting show the new
  name and sign out their users once; set `APP_NAME` to keep the previous name. The local Docker Compose project is
  renamed too, so the local development database starts empty.
- The interface settings moved from the gear button in the top bar into the user menu, next to **Profile** and
  **Sign out**.
- The profile page looks like the rest of the application: its sections, fields, buttons, confirmation dialogs and
  "Saved" notifications match the other pages, and the contacts table is no longer boxed inside another box.

### Fixed

- User menu showing an extra header with the name of the user when the interface is in Italian or Spanish, but not
  in English.
- Saving a new profile photo leading to an unstyled page outside the application.
- Profile photo not shown in the top bar, which always showed the initials of the user.
- Email field of the profile page not visible, and the warnings shown to the root user on that page not styled
 .
- **Delete account** and **Log out other browser sessions** dialogs of the profile page shown behind their dark
  overlay.
- Adding a primary contact from the profile page leaving the previous primary contact marked as primary too
 .
- Title of the profile page always shown in English ("Edit Profile").

## [1.0.0-alpha7] - 2026-11-14

### Added

- **Dashboard map coloring**: color the territories by zone, availability, assignment frequency over the last 12
  months or progress in a campaign, with a legend counting the territories of each color. Clicking a territory shows
  its zone, its status and a link to its page.
- **PR previews**: every pull request gets its own preview site at `https://pr-<N>.<site>.altervista.org`, filled with
  dummy data, updated on every push and removed when the pull request is closed (see the README).
- Scheduled tasks and queue of the PR previews, run by cron-job.org every 5 minutes from 8:00 to 22:55 (Rome time),
  Monday to Friday: the previews need the new `CRON_JOB_ORG_API_KEY` secret of the `PREVIEW` environment (see the
  README).
- Back button at the bottom of the documentation sidebar, leading to the app, or to the welcome page for visitors who
  are not signed in.

### Changed

- **BREAKING**: The remote Artisan endpoints moved from the `artisan.` subdomain to the `/api/artisan` path (e.g.
  `https://example.com/api/artisan/queue/process`): update the URLs called by your cron services.
- **BREAKING**: Building the frontend assets requires Node.js 20.19 or newer, needed by Vite 8.
- Upgraded to Filament 5 and Livewire 4, together with the Filament plugins (among them the knowledge base, now at
  version 3, and the activity log, now at version 4).
- The **Logs** page (Developer group, super administrators only) uses a new log viewer, since the previous one does
  not support Filament 5: log entries are listed in a table with level tabs, a date filter and stack traces.
- `db:wipe`, also run by `migrate:fresh`, only drops the tables with the prefix of the installation (`DB_PREFIX`),
  and refuses to run without a prefix when the database also holds the tables of other installations.
- Updated the dependencies, among them Laravel to 12.72, the impersonation plugin (`stechstudio/filament-impersonate`)
  to version 5, Vite to version 8 and `laravel-vite-plugin` to version 3.

### Removed

- Downloading log files from the **Logs** page, not supported by its new log viewer.

### Fixed

- Territory import results (the notification, the reasons of the failed rows and the downloadable CSV) shown partly
  in English and partly in the default language of the application, instead of the language of the user who started
  the import.
- Tags duplicated when saving a territory or a zone with the interface in a language other than English: the Tags page
  listed them twice and the tag filters did not find them.
- Assignments of a terminated campaign failing to save when edited, for example to return the territory, with "The
  selected campaign is invalid". Assigning a territory to a terminated campaign is still refused, now everywhere and
  not only in the forms.
- Dashboard failing to load when a zone with territories on the map has no color.
- Clicking the dashboard map showing debug notifications and zooming back out to all the territories.
- Zone names in the layers menu of the dashboard map showing raw HTML code.
- Sorting territories by assignment duration, and grouping them by campaign, failing when the database tables are
  prefixed (`DB_PREFIX`).

### Security

- Critical Laravel vulnerability allowing the API token guard to be bypassed with non-string credentials, along with
  the other security advisories reported for the dependencies.

## [1.0.0-alpha6] - 2026-11-11

### Added

- **User guide** covering every feature of the application, in English and Italian, with screenshots and
  step-by-step walkthroughs for the first setup, campaigns and publishers.
- **Welcome page** describing the project and its features, with links to the GitHub repository, the user guide and
  the login page. Available in English, Italian and Spanish.
- `ENABLE_SHARED_PII` environment variable to show personal data (assignee name and territory notes) on publicly
  shared assignment pages, where it is now hidden by default.

### Changed

- Notification emails are sent through the queue instead of during the request: on hosts without a queue worker,
  they are only sent when a cron service calls the queue endpoint (`/queue/process`).
- Users who cannot log in no longer receive the notification of a new campaign.
- Assignees are sorted by name when assigning territories.

### Fixed

- Application version stuck at `v1.0.0-alpha3` in the sidebar.
- Home page failing with a server error when using the built frontend assets.
- Publicly shared assignment pages failing with a server error when using the built frontend assets.
- Error notifications of the assign and return territory actions never being shown.
- Users allowed to list all assignments only seeing their own, because the roles seeder used an outdated permission
  name.
- Already assigned territories being offered when adding an assignment to a campaign.
- Campaign filters returning wrong results when combined with other conditions.
- Campaign overview widget failing when there are no territories, exceeding 100% when a territory was assigned more
  than once, and always showing the remaining-time warning.
- Stale assignment emails mentioning the wrong territory.
- Territory import rejecting every territory of an existing zone as a duplicate, and "update existing" ignoring the
  zone.
- Editing a user requiring a new password to be entered.
- Emails sent through the native `mail()` transport having duplicated `To` and `Subject` headers and no MIME content
  type.
- Territories having more than one assignment in progress: assigning, reopening or restoring an assignment is now
  refused when the territory is already assigned.
- Sorting territories by assignment duration failing on databases other than MySQL.
- Links in the emails sent through the queue and scheduler endpoints pointing to the `artisan.` subdomain instead of
  the application URL.
- Territories table loading slowly with many territories.
- High memory usage of the stale assignment detection, which loaded every assignment in progress at once.
- Database seeding failing with a cached configuration or without the development dependencies installed.
- Log files growing quickly because of debug messages written on every render of the territory form.
- Deleting a user leaving their team memberships behind.
- Saving a territory erasing its map, even when only other fields were changed.
- Creating a territory failing, and an image uploaded while creating it not being linked to the territory.
- Assignments in progress failing to save when edited, with "The selected territory is invalid".
- Global search results hidden under the top bar on tablets and desktops.
- Campaign status showing "Almost Expired" for every campaign ending in the future, and never "Expiring". It now shows
  "Expiring" from 7 days before the end date and "Almost Expired" from 3 days before.
- Territory map staying a grey box, without tiles or polygon, until it was scrolled fully into view, for example after
  switching from the image tab or on phones.

### Security

- **BREAKING**: The queue and scheduler endpoints called by external cron services (`/queue/process` and
  `/schedule/process`) could be called by anyone: they now require an `X-Cron-Token` header matching the new
  `ARTISAN_CRON_TOKEN` environment variable, and are rate limited.
- Only super administrators can impersonate users, and the `root` user can no longer be impersonated.
- Only super administrators can assign roles, so users can no longer grant themselves the `super_admin` role
 .
- The API tokens page is only available when the API feature is enabled, the API endpoints check the permissions of
  the user, and the territories and zones API is read-only.
- The token protecting the remote Artisan endpoints is compared in constant time, an empty token is rejected and the
  submitted token is no longer logged.
- Email bodies, including password reset links, are no longer written to the logs.
- The assign and return territory actions check the permissions of the user.
- The database password is no longer written to the logs when running the migrations.
- Territory import only downloads images from public hosts (also after redirects), with timeouts and a 10 MB size
  limit, and deletes invalid downloads.
- The root `.htaccess` denies every request when `mod_rewrite` is not available, instead of serving the project files
  (e.g. `.env`).
- The assignments shown on the territory, campaign and user pages follow the same visibility rules as the assignments
  list: users who cannot list every assignment only see their own.

## [1.0.0-alpha5] - 2026-09-16

### Added

- **Territory import** from a CSV file, with name, description, zone, notes, commercial flag, map polygon (GeoJSON)
  and image URL. Rows duplicating a territory of the same zone are rejected.
- **User documentation** (knowledge base) at `/knowledge-base`, in English and Italian, readable without logging in.
- **Campaign details page** with an overview widget, an assignments chart and a page to manage and bulk-terminate the
  campaign assignments. The territories table can also be grouped by campaign.
- Territory image upload from a URL, as an alternative to uploading a file.
- Appearance settings: each user can choose the font, font size, accent color, layout and density of the interface.
- Bottom navigation bar on mobile devices.
- Highlighted (solid) navigation icon for the current page.
- Emergency contacts (phone number and/or address) for users, editable by each user from the profile page and by
  administrators from the "Users" menu.
- Date and time of the last commit next to the version in the sidebar.
- Protection of the `root` user on non-production environments: nobody, not even `root` itself, can change its email,
  password or roles.

### Changed

- **BREAKING**: Upgraded to Laravel 12 and Filament 4, along with the other dependencies. Filament resources follow
  the Filament 4 directory structure (e.g. `app/Filament/Resources/Territories/TerritoryResource.php`).
- **BREAKING**: Territory names must be unique within their zone.
- The dashboard map uses `eduardoribeirodev/filament-leaflet`, compatible with Filament 4, instead of
  `webbingbrasil/filament-maps`, and colors the territories by zone only.
- Sidebar navigation reorganized: groups reordered, roles moved next to users, and assignments listed last in their
  group.

### Removed

- Artisan commands panel (`tomatophp/filament-artisan`), not compatible with Filament 4.

### Fixed

- Stats overview widget failing on SQLite databases.
- `root` user not receiving the `super_admin` role.
- Application version overflowing out of the sidebar.
- Creating a territory overwriting its image path.
- Territory form tabs hidden outside of the view page, and image tab not selected when no map is available.
- Missing styles on the guest pages, and broken styles in the interface.
- Documentation panel not being reachable.
- Wrong values in the example environment file.

### Security

- Remote code execution vulnerability in Livewire (CVE-2025-54068), fixed by updating it to 3.6.4.

## [1.0.0-alpha4] - 2025-06-23

### Fixed

- Map edit controls shown on read-only territory assignment views.

## [1.0.0-alpha3] - 2025-06-22

### Added

- Git branch and commit shown in the sidebar. On systems without `git`, they are read from the files set in the new
  `APP_VERSION_BRANCH_FILE` (default: `.git/HEAD`) and `APP_VERSION_COMMIT_FILE` (default: `.git/refs/heads/{branch}`)
  environment variables.

### Changed

- **BREAKING**: "Assignment types" renamed to "campaigns", and the `TerritoryAssignmentType` model to `Campaign`
 .
- Campaigns are optional when assigning a territory, and none is selected by default.
- Campaign validity dates are inclusive.
- The creator of a campaign is always recorded.
- The territory map in the assignment details is shown only when the territory has one.

### Fixed

- Territories being assignable to terminated campaigns.
- Sidebar not starting closed on mobile devices.
- "User profile" page not reachable by users without a verified email.
- Raw GeoJSON shown among the territory assignment details.
- Territory notes hidden from the assignment details when empty.

## [1.0.0-alpha2] - 2025-06-17

### Added

- **Multiple languages**, with English, Italian and Spanish translations.
- **Tag manager** to create and organize the tags of territories and zones, with per-type tabs, usage counters and
  translations.
- **Public link** to share the details of a territory assignment, viewable without logging in.
- Territory map in the territory assignment details.
- Current and past status column in the territories table, and tabs to filter the commercial territories.

### Fixed

- Sorting territories by assignee.

## [1.0.0-alpha1] - 2025-05-17

Complete rewrite of the application on top of Laravel 11, Filament 3 and Laravel Jetstream.

### Added

- Administration panel at `/app`, with login through Laravel Jetstream and Fortify.
- **Territories management**: creation, editing and deletion, image, notes, commercial flag, filters and a read-only
  view page.
- Territory polygon drawing and editing on an OpenStreetMap map, with selectable tile layers.
- Territory zones with a custom color and a list of their territories. Zones can also be created directly from the
  territory form.
- **Territory assignments**: assign and return territories, one at a time or in bulk, from both the territory page and
  the territories table, with full assignment history and a details page.
- **Campaigns** (named "assignment types" at the time) with validity dates, status badge, status filter and sorting.
- Dashboard with a global map of all territories, colored by zone or by assignment status, remembering the selected
  tile layer.
- Dashboard overview widget with the total number of territories and the average assignment duration over the last
  12 months.
- Users management, with first and last name, read-only view page and the registry of territories assigned to each
  user.
- Virtual users, which can be assigned territories but cannot log in.
- Roles and permissions management through Filament Shield, with policies for all resources. Users allowed to view
  only their own assignments see just those.
- Impersonation of other users.
- Tags for territories and zones.
- Global search across territories, zones and campaigns, matching name, description, zone, notes and tags.
- Activity log of user actions.
- In-app notifications when an assignment starts or ends, when a campaign is created, and when an assignment has been
  out for more than 60, 90, 120 or 150 days.
- Language switcher (Italian and English).
- Warning when leaving a page with unsaved changes.
- Live indicator of other users viewing or editing the same territory or zone (`discoverydesign/filament-gaze`).
- Viewers for the application logs and the sent emails.
- Environment indicator on non-production environments.
- Application version in the sidebar.
- Mail transport based on PHP's native `mail()` function, used as the primary mailer with failover.
- Token-protected HTTP API to run Artisan commands, the queue worker and the scheduled jobs on hosts without shell
  access, and an Artisan commands panel on test environments.
- Configurable database table prefix (`DB_PREFIX`) and engine (`DB_ENGINE`).

### Changed

- **BREAKING**: Rewrote the database structure from scratch. It is not compatible with the v1 database.
- Renamed the `testing` environment to `nightly`.

### Removed

- User self-registration: new users can only be created by an administrator.
- Teams and two-factor authentication.

### Security

- HTTPS is enforced everywhere except in local development.
- The API can only be used by authenticated users.
- Uploaded territory images are stored on a private disk.
