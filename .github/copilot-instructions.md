# Registro Territori - Laravel 11 + Filament V3 Application

Registro Territori is a **Laravel 11** application for managing territories for Jehovah's Witness congregations, built with **Filament V3** admin panel, **Laravel Jetstream** authentication, **Livewire 3** reactive components, **Vite 5** frontend tooling, and comprehensive testing via **Pest PHP**.

**ALWAYS follow these instructions first** and only fall back to search or additional context gathering if the information here is incomplete or found to be in error.

## Technology Stack

This application uses the following specific versions and technologies:

### Core Framework
- **PHP 8.2+**: Modern PHP features, improved performance, readonly classes
- **Laravel 11**: Latest LTS with improved validation, per-request rate limiting, enhanced Artisan commands
- **Composer 2.2+**: Dependency management with platform requirements checking

### Frontend & UI
- **Filament V3**: Modern admin panel with panels, resources, widgets, and advanced features
- **Laravel Jetstream**: Authentication scaffolding with teams, 2FA, session management
- **Livewire 3**: Full-stack reactive components with improved performance
- **Tailwind CSS**: Utility-first CSS framework
- **Vite 5**: Fast build tool with hot module replacement
- **Node.js 18+**: Required for Vite 5 and modern frontend tooling

### Database & Authentication
- **MySQL/SQLite**: Primary database with migrations and seeding
- **Laravel Sanctum**: API authentication and SPA auth
- **Filament Shield**: Role-based permissions for Filament panels

### Testing & Code Quality
- **Pest PHP**: Modern testing framework with Laravel integration
- **Laravel Pint**: Code formatting based on PHP-CS-Fixer
- **PHPUnit**: Underlying test runner for Pest

### Development Tools
- **Laravel Sail**: Docker development environment
- **Laravel IDE Helper**: Enhanced IDE support for Laravel
- **Docker**: Containerized development with PHP 8.2, MySQL, Redis

## CRITICAL WARNINGS
- **NEVER CANCEL BUILD OR TEST COMMANDS** - All build and test operations MUST complete fully
- **DOCKER BUILD ISSUES**: The Docker build (sail build) may fail in some environments due to network/repository access issues
- **ALWAYS USE ABSOLUTE PATHS** for SQLite database configuration in testing environments (only when needed, never propose)
- **PHP 8.2+ REQUIREMENT**: This application requires PHP 8.2 or higher - verify with `php --version`
- **LARAVEL 11 FEATURES**: Uses Laravel 11 with per-request rate limiting, improved validation, and enhanced Artisan commands
- **FILAMENT V3 SPECIFICS**: Uses Filament V3 with panels, resources, widgets, and Shield plugin for permissions
- **DEVELOPMENT TIMEOUT VALUES**: Set explicit timeouts of at least 10 minutes for Docker operations, 2 minutes for composer install, 1 minute for npm operations
- **GIT COMMIT**: Always use the [conventional commits](https://www.conventionalcommits.org/en/v1.0.0/) format for git commits!
- **GitHub Pull Requests**: follow the following rules:
  - Always use the [conventional commits](https://www.conventionalcommits.org/en/v1.0.0/) format for Github PRs titles!
  - The PR body should be brief and complete, all details should be posted as a new comment.


## Working Effectively

### Primary Setup Method (Preferred - Laravel Sail with Docker)
1. **Check for Apache conflicts**:
   ```bash
   systemctl is-active apache2 || echo "Apache not running (good)"
   # If Apache is running: sudo systemctl stop apache2
   ```

2. **Install PHP dependencies** (7+ minutes, NEVER CANCEL):
   ```bash
   docker run --rm \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php82-composer:latest \
       composer install --ignore-platform-reqs
   ```
   **TIMEOUT: Set 10+ minutes**. Fix permissions afterward: `sudo chown -R $(id -u):$(id -g) vendor/`

3. **Create environment file**:
   ```bash
   cp .env.example .env
   ```

4. Update the URLs in the `.env` file to match the port the application is running on (`docker-compose.yml` is configured to use port 80):
   ```bash
   APP_URL=http://localhost
   ASSET_URL=http://localhost
   ```

5. **Build Docker images** (2+ minutes, NEVER CANCEL):
   ```bash
   ./vendor/bin/sail build --no-cache
   ```
   **TIMEOUT: Set 10+ minutes**. **NOTE**: May fail in restricted environments - see Alternative Setup.

6. **Start containers**:
   ```bash
   ./vendor/bin/sail up -d
   ```

7. **Configure Laravel 11 application**:
   ```bash
   ./vendor/bin/sail composer install
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate
   ./vendor/bin/sail artisan db:seed
   ./vendor/bin/sail artisan db:seed --class=FakeDataSeeder
   ```

8. **Initialize Filament V3 and Jetstream setup**:
   ```bash
   # Publish Filament V3 assets and configuration
   ./vendor/bin/sail artisan filament:install --panels
   ./vendor/bin/sail artisan vendor:publish --tag=jetstream-views
   ```

9. **Create super admin user (Filament Shield)**:
   ```bash
   ./vendor/bin/sail artisan shield:super-admin --user=1 --panel=app
   ```

10. **Install frontend dependencies with Vite 5** (15+ seconds, NEVER CANCEL):
   ```bash
   ./vendor/bin/sail npm install && ./vendor/bin/sail npm run dev
   ```

### Alternative Setup Method (Without Docker)
**Use this when Docker build fails or Docker is unavailable:**

1. **Verify system requirements**:
   ```bash
   php --version  # Requires PHP 8.2+ (Laravel 11 requirement)
   composer --version  # Composer 2.2+
   node --version  # Requires Node.js 18+ (Vite 5 requirement)
   npm --version  # npm 9+
   ```

2. **Install dependencies**:
   ```bash
   composer install --ignore-platform-reqs  # 7+ minutes, NEVER CANCEL
   npm install  # 15+ seconds, NEVER CANCEL
   ```

3. **Configure Laravel 11 environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Update the URLs in the `.env` file to match the port the application is running on:
   ```bash
   APP_URL=http://localhost:8000
   ASSET_URL=http://localhost:8000
   ```

5. **Database setup (SQLite for testing)**:
   ```bash
   # Edit .env to use SQLite:
   DB_CONNECTION=sqlite
   DB_DATABASE=/absolute/path/to/database.sqlite
   
   touch database/database.sqlite
   php artisan migrate
   php artisan db:seed
   php artisan db:seed --class=FakeDataSeeder
   ```

6. **Initialize Filament V3 without Docker**:
   ```bash
   php artisan filament:install --panels
   php artisan vendor:publish --tag=jetstream-views
   ```

7. **Initialize root user as admin (Filament Shield)**:
   ```bash
   php artisan shield:super-admin --user=1 --panel=app
   ```

## Development Commands

### Starting the Application
- **Laravel 11 with Sail**: `./vendor/bin/sail up -d && ./vendor/bin/sail npm run dev`
- **Laravel 11 without Docker**: `php artisan serve --host=0.0.0.0 --port=8000` (runs on http://localhost:8000)
- **Vite 5 development server**: `npm run dev` (hot reloading for frontend assets with Livewire 3 support)
- **Jetstream dashboard**: Access at http://localhost:8000/dashboard (after login)
- **Filament V3 admin panel**: Access at http://localhost:8000/app (main admin interface)

### Building Assets
- **Vite 5 production build**: `npm run build` (2+ seconds, NEVER CANCEL)
- **Vite 5 development assets**: `npm run dev` (supports Livewire 3 hot reload)
- **Laravel 11 asset publishing**: `php artisan vendor:publish --tag=laravel-assets --ansi --force`

### Database Operations
- **Laravel 11 migrate**: `php artisan migrate` or `./vendor/bin/sail artisan migrate`
- **Fresh migration with seeding**: `php artisan migrate:fresh --seed`
- **Seed fake data**: `php artisan db:seed --class=FakeDataSeeder`
- **Database factories**: Use `php artisan tinker` then `User::factory(10)->create()`

## Testing with Pest PHP

### Running Tests (7+ seconds, NEVER CANCEL)
- **With Sail**: `./vendor/bin/sail test --parallel --drop-databases`
- **Without Docker**: `DB_CONNECTION=sqlite DB_DATABASE=/absolute/path/to/testing.sqlite ./vendor/bin/pest --parallel`
- **Create test database**: `touch database/testing.sqlite`
- **Run specific test**: `./vendor/bin/pest tests/Feature/UserTest.php`
- **Pest with coverage**: `./vendor/bin/pest --coverage`
- **TIMEOUT: Set 2+ minutes for test suite**

### Code Quality
- **Laravel Pint (formatting)**: `./vendor/bin/pint` (15+ seconds)
- **Check formatting**: `./vendor/bin/pint --test`

## Validation Scenarios

> [!NOTE]
> Unless specified otherwise, any reference to logging in as the "super admin user" always refers to the user with ID 1.
>
> This user is created during the installation process, and its credentials are stored in the `.env` file as:  
> - Email: `$INITIAL_ROOT_USERMAIL`
> - Password: `$INITIAL_ROOT_PASSWORD`

**ALWAYS manually validate changes through these complete user scenarios:**

1. **Filament V3 Admin Panel Access**:
   - Navigate to `${APP_URL}/app/login`
   - Login with created super admin user
   - Verify dashboard loads with widgets
   - Test Filament Shield permissions system

2. **Laravel Jetstream Authentication**:
   - Navigate to `${APP_URL}/login` (Jetstream login)
   - Test registration, password reset
   - Verify teams functionality if enabled
   - Test two-factor authentication setup

3. **Territory Management (Core Features)**:
   - Create a new territory zone through Filament admin
   - Add territories to the zone using Filament resources
   - Assign territories to users via Filament
   - Verify map integration displays territories correctly

4. **User Management & Permissions**:
   - Create new users through Filament V3 admin
   - Assign roles and permissions using Filament Shield
   - Test user login and access levels
   - Verify Livewire 3 components work correctly

5. **Data Export/Import**:
   - Test territory data export functionality via Filament actions
   - Verify assignment report generation
   - Test Excel/CSV export using PhpSpreadsheet integration

## Common Commands Reference

### Laravel 11 Artisan Commands
```bash
# Key generation and caching
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Database
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=FakeDataSeeder

# Filament V3 commands
php artisan filament:install --panels
php artisan filament:make-resource TerritoryResource
php artisan filament:make-page CustomPage
php artisan shield:super-admin --user=1 --panel=app

# Laravel Jetstream
php artisan jetstream:install livewire
php artisan vendor:publish --tag=jetstream-views

# Development
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work
php artisan schedule:work
php artisan tinker
```

### File Structure Overview
```
/app/Filament/         # Filament V3 admin panel components
  /Pages/              # Custom Filament pages (Dashboard, Profile, etc.)
  /Resources/          # Filament resources (Territory, User, etc.)
  /Widgets/           # Dashboard widgets and statistics
/app/Models/           # Eloquent models (User, Territory, TerritoryZone, Team, etc.)
/app/Http/Controllers/ # API and web controllers
/app/Providers/Filament/ # Filament panel providers (AppPanelProvider, GuestPanelProvider)
/database/migrations/  # Database schema migrations
/database/seeders/     # Database seeders including FakeDataSeeder
/resources/views/      # Blade templates (Jetstream views, custom views)
/resources/js/         # Frontend JavaScript (Livewire 3 components)
/resources/css/        # Frontend styles (Tailwind CSS)
/routes/api/           # API routes including artisan.php
/tests/Feature/        # Pest PHP feature tests
/tests/Unit/          # Pest PHP unit tests
/config/filament.php   # Filament V3 configuration
```

## CI/CD Integration

The application includes GitHub Actions workflows in `.github/workflows/`:
- `build.yml` - Production builds
- `run-tests.yml` - Test execution with coverage
- `check-translations.yml` - Translation validation

**Before committing**:
```bash
./vendor/bin/pint  # Laravel Pint formatting
./vendor/bin/pest  # Pest PHP tests
npm run build      # Vite 5 production build
```

## Known Issues and Workarounds

1. **Docker Build Failures**: In restricted environments, Docker builds may fail due to network access. Use the Alternative Setup Method.

2. **SQLite Path Issues**: Always use absolute paths for SQLite databases in testing: 
   ```bash
   DB_DATABASE=/full/path/to/database.sqlite
   ```

3. **Permission Issues**: After Docker operations, fix file permissions:
   ```bash
   sudo chown -R $(id -u):$(id -g) vendor/ storage/ bootstrap/cache/
   ```

4. **Filament V3 Panel Configuration**: Ensure panels are properly registered in `AppPanelProvider.php`

5. **Livewire 3 + Vite 5**: Hot reloading requires proper Vite configuration for Livewire components

6. **Laravel 11 + PHP 8.2**: Some legacy packages may require compatibility updates

7. **Test Database Configuration**: Pest PHP tests require proper SQLite configuration with absolute paths.

## Performance Notes

- **Composer install**: ~7 minutes (includes IDE helper generation for Laravel 11)
- **npm install**: ~15 seconds (Vite 5 + Tailwind CSS)
- **npm run build**: ~2 seconds (Vite 5 production build)
- **Laravel 11 migrations**: <1 second
- **Pest PHP test suite**: ~7 seconds (parallel execution)
- **Laravel Pint formatting**: ~15 seconds
- **Docker build**: 2+ minutes (when successful)
- **Filament V3 resource generation**: <1 second per resource

## Filament V3 Development Guidelines

### Creating Filament Resources
```bash
# Generate a new resource with all CRUD operations
php artisan filament:make-resource Territory --generate

# Create resource with custom form and table
php artisan filament:make-resource TerritoryZone --view

# Generate relation manager
php artisan filament:make-relation-manager TerritoryResource territories
```

### Filament Widgets and Pages
```bash
# Create custom dashboard widget
php artisan filament:make-widget StatsOverview --resource=TerritoryResource

# Create custom page
php artisan filament:make-page TerritoryAnalytics

# Create settings page
php artisan filament:make-page ManageSettings --type=manage-records
```

### Working with Filament Shield (Permissions)
```bash
# Generate permissions for all resources
php artisan shield:generate

# Create custom permissions
php artisan shield:make-permission custom_permission

# Assign super admin to user
php artisan shield:super-admin --user=1 --panel=app
```

**REMEMBER**: NEVER CANCEL any build, test, or install command. Allow all operations to complete fully.
