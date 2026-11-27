<?php

use App\Filament\Setup\Pages\SetupWizard;
use App\Models\User;
use App\Notifications\TestEmailNotification;
use App\Notifications\UserInvitationNotification;
use App\Settings\GeneralSettings;
use App\Settings\SecuritySettings;
use App\Settings\TerritorySettings;
use App\Support\EnvironmentFile;
use App\Support\HttpsCertificate;
use App\Support\Installation;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();

    // The keys of the AI assistants exist: the Configuration step would create them otherwise
    usePassportTestKeys();
    Filament::setCurrentPanel('setup');

    config(['app.setup.enabled' => true, 'app.setup.token' => 'the-setup-token']);
    @unlink(Installation::markerPath());
    Installation::forget();

    // The `.env` file of the wizard is a temporary one, where only what it holds is set
    $this->envPath = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\n");
    app()->instance(EnvironmentFile::class, new class($this->envPath) extends EnvironmentFile
    {
        public function isMissing(string $key): bool
        {
            return blank($this->fileValues()[$key] ?? null);
        }
    });

    app()->instance(HttpsCertificate::class, new class extends HttpsCertificate
    {
        public function inspect(string $host, int $port = 443): array
        {
            return ['valid' => true, 'expires_at' => now()->addYear()->toImmutable(), 'error' => null];
        }
    });
});

afterEach(function () {
    array_map(unlink(...), glob($this->envPath.'.backup-*') ?: []);
    @unlink($this->envPath);
    @unlink(Installation::markerPath());
    Installation::forget();
});

/**
 * Open the wizard as if the setup token had been typed.
 */
function unlockSetupWizard(): void
{
    session()->put(SetupWizard::UNLOCKED_SESSION_KEY, hash('sha256', 'the-setup-token'));
}

/**
 * Set every value of `.env` that the wizard asks for, as after its Configuration step.
 */
function completeSetupEnvironmentFile(string $path): void
{
    $connection = config('database.default');
    $values = [
        'DB_CONNECTION' => $connection,
        'DB_DATABASE' => config("database.connections.{$connection}.database"),
        'DB_PORT' => '3306',
        'MAIL_MAILER' => 'log',
        'MAIL_PORT' => '587',
        'MAIL_FROM_ADDRESS' => 'noreply@example.com',
        'QUEUE_CONNECTION' => 'sync',
    ];

    file_put_contents($path, "SETUP_TOKEN=the-setup-token\n".collect(array_keys(SetupWizard::ENVIRONMENT_VARIABLES))
        ->map(fn (string $variable): string => $variable.'='.($values[$variable] ?? 'set')."\n")
        ->implode('')
        ."ARTISAN_CRON_TOKEN=cron-token\n"
        ."INITIAL_ROOT_USERNAME=\"Root User\"\nINITIAL_ROOT_USERMAIL=root@example.com\nINITIAL_ROOT_PASSWORD=old-password\n");
}

/**
 * The values of the wizard for a complete installation.
 *
 * @return array<string, mixed>
 */
function setupWizardData(array $overrides = []): array
{
    return [
        'root_first_name' => 'Anna',
        'root_last_name' => 'Rossi',
        'root_email' => 'anna@example.com',
        'root_password' => 'Root-Pass-2026!',
        'root_password_confirmation' => 'Root-Pass-2026!',
        'app_name' => ' Congregation North ',
        'kingdom_hall_address' => 'Main Street 1',
        'primary_color' => '#2563eb',
        'administrators' => [],
        'ai_assistants_enabled' => true,
        'sharing_enabled' => true,
        'guest_returns' => true,
        'require_two_factor' => true,
        'require_email_verification' => true,
        ...$overrides,
    ];
}

test('the application is not installed until it has the root user', function () {
    expect(Installation::isInstalled())->toBeFalse();

    User::factory()->create(['id' => 1]);
    Installation::forget();

    expect(Installation::isInstalled())->toBeTrue()
        ->and(file_exists(Installation::markerPath()))->toBeTrue();
});

test('the wizard can be turned off', function () {
    config(['app.setup.enabled' => false]);
    Installation::forget();

    expect(Installation::needsSetup())->toBeFalse();
});

test('an unreachable database shows the wizard only during an installation', function () {
    $defaultConnection = config('database.default');
    config([
        'database.connections.unreachable' => ['driver' => 'sqlite', 'database' => '/nonexistent/database.sqlite'],
        'database.default' => 'unreachable',
    ]);

    expect(Installation::isInstalled())->toBeFalse();

    // Without a token, it is an installed application whose database is down: its usual error page is shown
    config(['app.setup.token' => null]);
    Installation::forget();

    expect(Installation::isInstalled())->toBeTrue();

    config(['database.default' => $defaultConnection]);
});

test('the pages open the wizard until the application is installed', function () {
    // First: the pages of the app panel opened by a test stay registered for the next requests of the same test
    expect(SetupWizard::getUrl(panel: 'setup'))->toBe(url('/setup'));
    $this->get('/setup')->assertOk();

    $this->get('/')->assertRedirect(SetupWizard::getUrl(panel: 'setup'));
    $this->get('/app/login')->assertRedirect(SetupWizard::getUrl(panel: 'setup'));

    User::factory()->create(['id' => 1]);
    Installation::forget();

    $this->get('/app/login')->assertOk();
    $this->get(SetupWizard::getUrl(panel: 'setup'))->assertForbidden();
});

test('without a setup token the wizard explains how to add one', function () {
    config(['app.setup.token' => null]);

    Livewire::test(SetupWizard::class)
        ->assertSee(__('setup.token.missing_heading'))
        ->assertDontSee(__('setup.token.label'))
        ->assertDontSee(__('setup.steps.requirements.label'));
});

test('the wizard opens only with the setup token', function () {
    Livewire::test(SetupWizard::class)
        ->assertSee(__('setup.token.label'))
        ->assertDontSee(__('setup.steps.requirements.description'))
        ->fillForm(['token' => 'wrong'], 'tokenForm')
        ->call('unlockWizard')
        ->assertHasErrors(['unlock.token'])
        ->fillForm(['token' => 'the-setup-token'], 'tokenForm')
        ->call('unlockWizard')
        ->assertHasNoErrors()
        ->assertSee(__('setup.steps.requirements.description'));
});

test('the attempts at the setup token are limited', function () {
    $component = Livewire::test(SetupWizard::class);

    foreach (range(1, SetupWizard::TOKEN_ATTEMPTS_PER_MINUTE) as $attempt) {
        $component->fillForm(['token' => 'wrong'], 'tokenForm')->call('unlockWizard');
    }

    $component->fillForm(['token' => 'the-setup-token'], 'tokenForm')
        ->call('unlockWizard')
        ->assertHasErrors(['unlock.token'])
        ->assertDontSee(__('setup.steps.requirements.description'));
});

test('the wizard cannot be completed without the setup token', function () {
    Livewire::test(SetupWizard::class)
        ->call('complete')
        ->assertForbidden();

    expect(User::query()->count())->toBe(0);
});

test('the wizard checks the requirements of the server', function () {
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->assertSee(__('installation.checks.php_version.label'))
        ->assertSee(__('installation.checks.php_extensions.label'))
        ->assertSee(__('installation.checks.https.label'))
        ->assertSee(__('installation.checks.env_file.label'))
        ->assertSee(__('setup.steps.counter', ['step' => 1, 'total' => 9]))
        ->goToWizardStep(2)
        ->assertWizardCurrentStep(2);
});

test('only the missing values of .env are asked for and written', function () {
    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\nAPP_LOCALE=it\nAPP_TIMEZONE=\nDB_CONNECTION=".config('database.default')."\nDB_HOST=x\nDB_PORT=1\nDB_DATABASE=x\nDB_USERNAME=x\nDB_PASSWORD=x\nMAIL_MAILER=log\n");
    unlockSetupWizard();

    $component = Livewire::test(SetupWizard::class)
        ->assertSet('missingVariables', ['APP_URL', 'APP_TIMEZONE', 'DB_PREFIX', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'QUEUE_CONNECTION'])
        ->assertSchemaComponentVisible('app_timezone', 'form')
        ->assertSchemaComponentHidden('app_locale', 'form')
        // The values already set are shown (but the passwords are never sent to the browser): only the database and the
        // mailer can be changed
        ->assertFormFieldEnabled('db_database')
        ->assertSchemaStateSet(['db_database' => 'x'], 'form')
        ->assertFormFieldEnabled('mail_mailer')
        ->assertSet('changeableVariables', ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'MAIL_MAILER'])
        ->assertSchemaStateSet(['db_password' => null], 'form')
        // Not for the mailer of the tests
        ->assertSchemaComponentHidden('mail_host', 'form')
        ->fillForm([
            'app_url' => 'https://congregation.example.com/',
            'app_timezone' => 'Europe/Rome',
            'mail_from_address' => 'noreply@example.com',
        ])
        ->goToWizardStep(3)
        ->assertWizardCurrentStep(3);

    // The keys of the push notifications, missing too, are created
    expect(file_get_contents($this->envPath))->toStartWith(
        "SETUP_TOKEN=the-setup-token\nAPP_LOCALE=it\nAPP_TIMEZONE=Europe/Rome\nDB_CONNECTION=".config('database.default')."\nDB_HOST=x\nDB_PORT=1\nDB_DATABASE=x\nDB_USERNAME=x\nDB_PASSWORD=x\nMAIL_MAILER=log\n"
        ."APP_URL=https://congregation.example.com\nMAIL_FROM_ADDRESS=noreply@example.com\nVAPID_PUBLIC_KEY=",
    );

    // The queue is saved by the Background tasks step
    $component->assertSet('missingVariables', ['DB_PREFIX', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'QUEUE_CONNECTION']);
});

test('the mailer can be changed, among the mailers of the configuration', function () {
    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\nAPP_URL=https://congregation.example.com\nAPP_TIMEZONE=UTC\nAPP_LOCALE=en\nDB_CONNECTION=".config('database.default')."\nDB_HOST=x\nDB_PORT=1\nDB_DATABASE=x\nDB_USERNAME=x\nDB_PASSWORD=x\nMAIL_MAILER=log\nMAIL_FROM_ADDRESS=noreply@example.com\n");
    unlockSetupWizard();

    expect(array_keys(SetupWizard::mailerOptions()))->toBe(array_keys(config('mail.mailers')));

    Livewire::test(SetupWizard::class)
        ->fillForm([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => '587',
        ])
        ->assertSchemaComponentVisible('mail_host', 'form')
        ->goToWizardStep(3)
        ->assertWizardCurrentStep(3);

    expect(file_get_contents($this->envPath))
        ->toContain("\nMAIL_MAILER=smtp\n")
        ->not->toContain('MAIL_MAILER=log')
        ->toContain("MAIL_HOST=smtp.example.com\nMAIL_PORT=587\n")
        ->and(config('mail.default'))->toBe('smtp');
});

test('the values with a fallback in the configuration can be left empty', function () {
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->assertSchemaComponentVisible('db_host', 'form')
        ->fillForm(collect(SetupWizard::ENVIRONMENT_VARIABLES)->keys()->mapWithKeys(fn (string $variable): array => [strtolower($variable) => null])->all())
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(3);

    // Nothing is written: the fallbacks apply
    expect(file_get_contents($this->envPath))->toMatch('/^SETUP_TOKEN=the-setup-token\nVAPID_PUBLIC_KEY=\S+\nVAPID_PRIVATE_KEY=\S+\n$/');
});

test('the keys of the push notifications are created only when both are missing', function () {
    unlockSetupWizard();

    // Kept as they are: replacing them would unsubscribe every device
    foreach (["VAPID_PUBLIC_KEY=public\nVAPID_PRIVATE_KEY=private\n", "VAPID_PUBLIC_KEY=public\n"] as $keys) {
        completeSetupEnvironmentFile($this->envPath);
        file_put_contents($this->envPath, $keys, FILE_APPEND);
        $before = file_get_contents($this->envPath);

        Livewire::test(SetupWizard::class)
            ->assertSeeHtml('data-key="push" data-status="set"')
            ->goToWizardStep(3)
            ->assertWizardCurrentStep(3);

        expect(file_get_contents($this->envPath))->toBe($before);
    }

    completeSetupEnvironmentFile($this->envPath);

    Livewire::test(SetupWizard::class)
        ->assertSeeHtml('data-key="push" data-status="created"')
        ->goToWizardStep(3);

    $keys = (new EnvironmentFile($this->envPath))->fileValues();

    expect($keys['VAPID_PUBLIC_KEY'])->toHaveLength(87)
        ->and($keys['VAPID_PRIVATE_KEY'])->toHaveLength(43)
        ->and(config('webpush.vapid.public_key'))->toBe($keys['VAPID_PUBLIC_KEY']);
});

test('the database settings can be changed, keeping the password left empty', function () {
    $connection = config('database.default');
    $database = config("database.connections.{$connection}.database");

    // The settings of the database of the tests (but the password), so that it can still be reached
    $settings = collect(['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_USERNAME' => 'username'])
        ->map(fn (string $key): mixed => config("database.connections.{$connection}.{$key}"))
        ->filter(fn (mixed $value): bool => filled($value))
        ->map(fn (mixed $value, string $variable): string => "{$variable}={$value}\n")
        ->implode('');

    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\nDB_CONNECTION={$connection}\nDB_DATABASE={$database}\n{$settings}DB_PASSWORD=secret\nDB_PREFIX=old_\n");
    config(["database.connections.{$connection}.prefix" => 'old_']);
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->assertFormFieldEnabled('db_prefix')
        ->assertFormFieldEnabled('db_password')
        ->assertSchemaStateSet(['db_prefix' => 'old_', 'db_password' => null], 'form')
        ->fillForm(['db_prefix' => 'och_'])
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(3);

    expect(file_get_contents($this->envPath))
        ->toContain("\nDB_PREFIX=och_\n")
        ->toContain("\nDB_PASSWORD=secret\n")
        ->toContain("\nDB_DATABASE={$database}\n")
        ->and(config("database.connections.{$connection}.prefix"))->toBe('och_');
});

test('a database that cannot be reached stops the wizard', function () {
    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\nAPP_URL=https://congregation.example.com\nAPP_TIMEZONE=UTC\nAPP_LOCALE=en\nMAIL_MAILER=log\nMAIL_FROM_ADDRESS=noreply@example.com\n");
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm([
            'db_connection' => 'pgsql',
            'db_host' => '127.0.0.1',
            'db_port' => '1',
            'db_database' => 'och',
            'db_username' => 'och',
            'db_password' => 'secret',
        ])
        ->goToWizardStep(3)
        ->assertNotified(__('setup.environment.database_failed'))
        ->assertWizardCurrentStep(2);

    expect(file_get_contents($this->envPath))->not->toContain('DB_');
});

test('a test email can be sent to the root user', function () {
    Notification::fake();
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm(['root_email' => 'anna@example.com'])
        ->callAction(TestAction::make('sendTestEmail')->schemaComponent('rootActions', 'form'))
        ->assertNotified(__('setup.checks.test_email.sent', ['email' => 'anna@example.com']))
        ->assertSet('testEmailSentTo', 'anna@example.com')
        ->assertFormFieldEnabled('require_email_verification');

    Notification::assertSentOnDemand(TestEmailNotification::class);
});

test('the .env file is copied before the wizard first changes it', function () {
    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\nAPP_LOCALE=it\n");
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm(['app_url' => 'https://congregation.example.com', 'app_timezone' => 'Europe/Rome', 'mail_mailer' => 'log', 'mail_from_address' => 'noreply@example.com'])
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->fillForm(['queue_connection' => 'database'])
        ->goToWizardStep(4);

    $backups = glob($this->envPath.'.backup-*');

    // Only once, as the file was before
    expect($backups)->toHaveCount(1)
        ->and(file_get_contents($backups[0]))->toBe("SETUP_TOKEN=the-setup-token\nAPP_LOCALE=it\n")
        ->and(file_get_contents($this->envPath))->toContain('QUEUE_CONNECTION=database');
});

test('the configuration step warns when APP_URL is not the address of the page', function () {
    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\nAPP_URL=https://other.example.com\n");
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->assertSee(__('setup.environment.app_url_mismatch', ['url' => 'https://other.example.com', 'page' => 'http://localhost']));

    // The default port is the same address
    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\nAPP_URL=http://localhost:80/\n");

    Livewire::test(SetupWizard::class)
        ->assertDontSee(__('setup.environment.app_url_mismatch', ['url' => 'http://localhost:80/', 'page' => 'http://localhost']));
});

test('the administrators step tells when the invitations would not be sent', function () {
    config(['mail.default' => 'log']);
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->assertSee(__('setup.administrators.mailer_not_sending', ['mailer' => 'log']));
});

test('the database can be tested from the configuration step, before saving', function () {
    completeSetupEnvironmentFile($this->envPath);
    $environment = file_get_contents($this->envPath);
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->callAction(TestAction::make('testDatabase')->schemaComponent('databaseActions', 'form'))
        ->assertNotified(__('setup.environment.database_ok'))
        ->fillForm(['db_connection' => 'pgsql', 'db_host' => '127.0.0.1', 'db_port' => '1', 'db_database' => 'och', 'db_username' => 'och'])
        ->callAction(TestAction::make('testDatabase')->schemaComponent('databaseActions', 'form'))
        ->assertNotified(__('setup.environment.database_failed'));

    // Nothing is saved
    expect(file_get_contents($this->envPath))->toBe($environment);
});

test('a test email can be sent from the configuration step, with the mailer chosen', function () {
    Notification::fake();
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm(['mail_mailer' => 'array', 'mail_from_address' => 'noreply@example.com'])
        ->callAction(TestAction::make('sendConfigurationTestEmail')->schemaComponent('mailActions', 'form'), ['email' => 'anna@example.com'])
        ->assertNotified(__('setup.checks.test_email.sent', ['email' => 'anna@example.com']))
        ->assertSet('testEmailSentTo', 'anna@example.com');

    Notification::assertSentOnDemand(TestEmailNotification::class);
    expect(config('mail.default'))->toBe('array');
});

test('the test email is sent also before the database has its tables', function () {
    Schema::drop('mail_logs');
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm(['mail_mailer' => 'array', 'mail_from_address' => 'noreply@example.com'])
        ->callAction(TestAction::make('sendConfigurationTestEmail')->schemaComponent('mailActions', 'form'), ['email' => 'anna@example.com'])
        ->assertNotified(__('setup.checks.test_email.sent', ['email' => 'anna@example.com']));
});

test('the database step warns about the tables of another application', function () {
    completeSetupEnvironmentFile($this->envPath);
    unlockSetupWizard();
    session()->put(SetupWizard::PROGRESS_SESSION_KEY.'.step', 2);

    // The tables of an earlier installation of the app are not another application
    Livewire::test(SetupWizard::class)
        ->assertDontSee(__('setup.database.foreign_tables_heading'));

    DB::table('migrations')->delete();

    // With a prefix the tables of the app never mix with the others
    DB::connection()->getTablePrefix() === ''
        ? Livewire::test(SetupWizard::class)->assertSee(__('setup.database.foreign_tables_heading'))
        : Livewire::test(SetupWizard::class)->assertDontSee(__('setup.database.foreign_tables_heading'));
});

test('the root user step tells when the mailer does not send the emails', function () {
    unlockSetupWizard();

    config(['mail.default' => 'log']);
    Livewire::test(SetupWizard::class)
        ->assertSee(__('setup.root.mailer_not_sending', ['mailer' => 'log']));

    config(['mail.default' => 'smtp']);
    Livewire::test(SetupWizard::class)
        ->assertDontSee(__('setup.root.mailer_not_sending', ['mailer' => 'smtp']));
});

test('requiring the email confirmation needs a test email', function () {
    completeSetupEnvironmentFile($this->envPath);
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->assertFormFieldDisabled('require_email_verification')
        ->fillForm(setupWizardData())
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(app(SecuritySettings::class)->refresh()->require_email_verification)->toBeFalse();
});

test('completing the wizard installs the application', function () {
    completeSetupEnvironmentFile($this->envPath);
    Notification::fake();
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm(['root_email' => 'anna@example.com'])
        ->callAction(TestAction::make('sendTestEmail')->schemaComponent('rootActions', 'form'))
        ->fillForm(setupWizardData([
            'administrators' => [
                ['first_name' => 'Marco', 'last_name' => 'Bianchi', 'email' => 'marco@example.com'],
            ],
        ]))
        ->call('complete')
        ->assertHasNoFormErrors()
        ->assertSet('completed', true)
        ->assertSee(__('setup.completed.heading'))
        ->assertSee(__('setup.completed.items.root', ['email' => 'anna@example.com']))
        ->assertSee(trans_choice('setup.completed.items.administrators', 1, ['count' => 1]))
        ->assertSee(__('setup.completed.items.two_factor'))
        ->assertSee(__('setup.completed.items.token_removed'))
        ->assertSee(str(__('setup.completed.items.env_backup', ['file' => '']))->beforeLast(':')->toString());

    $root = User::rootUser();
    $administrator = User::query()->where('email', 'marco@example.com')->firstOrFail();

    expect($root->email)->toBe('anna@example.com')
        ->and($root->hasVerifiedEmail())->toBeTrue()
        ->and($root->hasRole('super_admin'))->toBeTrue()
        ->and(password_verify('Root-Pass-2026!', $root->password))->toBeTrue()
        ->and($administrator->hasRole('super_admin'))->toBeTrue()
        ->and($administrator->password)->toBeNull()
        ->and(app(GeneralSettings::class)->refresh()->app_name)->toBe('Congregation North')
        ->and(app(SecuritySettings::class)->refresh()->require_two_factor)->toBeTrue()
        ->and(app(SecuritySettings::class)->require_email_verification)->toBeTrue()
        ->and(app(TerritorySettings::class)->refresh()->guest_returns)->toBeTrue()
        ->and(file_exists(Installation::markerPath()))->toBeTrue()
        // The root user just created replaces the one of `.env`
        ->and(file_get_contents($this->envPath))->toContain("\nINITIAL_ROOT_USERNAME=\"Anna Rossi\"\nINITIAL_ROOT_USERMAIL=anna@example.com\nINITIAL_ROOT_PASSWORD=\"Root-Pass-2026!\"\n")
        ->and(Installation::needsSetup())->toBeFalse()
        // The setup token is no longer used
        ->and(file_get_contents($this->envPath))->not->toContain('SETUP_TOKEN')
        ->and(session()->has(SetupWizard::PROGRESS_SESSION_KEY))->toBeFalse();

    Notification::assertSentTo($administrator, UserInvitationNotification::class);
});

test('the timezone of the browser is the default of the missing APP_TIMEZONE', function () {
    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\nAPP_TIMEZONE=\n");
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->call('useBrowserTimezone', 'Not/A_Timezone')
        ->assertSchemaStateSet(['app_timezone' => 'UTC'], 'form')
        ->call('useBrowserTimezone', 'Europe/Rome')
        ->assertSchemaStateSet(['app_timezone' => 'Europe/Rome'], 'form')
        // Only once: never over the choice of the user
        ->fillForm(['app_timezone' => 'Europe/Paris'])
        ->call('useBrowserTimezone', 'Europe/Rome')
        ->assertSchemaStateSet(['app_timezone' => 'Europe/Paris'], 'form');
});

test('the timezone of the browser does not replace APP_TIMEZONE when it is set', function () {
    completeSetupEnvironmentFile($this->envPath);
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->call('useBrowserTimezone', 'Europe/Rome')
        ->assertSet('browserTimezoneUsed', false);
});

test('a password can be generated for the root user', function () {
    unlockSetupWizard();

    $component = Livewire::test(SetupWizard::class)
        ->assertSee(__('setup.root.password_hint', ['min' => 8]))
        ->callAction(TestAction::make('generatePassword')->schemaComponent('root_password', 'form'));

    $password = $component->get('data.root_password');

    expect($password)->toHaveLength(20)
        ->and($component->get('data.root_password_confirmation'))->toBe($password);

    // Never in the notification
    $component->assertNotified(FilamentNotification::make()
        ->success()
        ->title(__('setup.root.password_generated'))
        ->body(__('setup.root.password_generated_body'))
        ->persistent());
});

test('reloading the page keeps the values typed and goes on from the last step completed', function () {
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm([
            'root_first_name' => 'Anna',
            'root_password' => 'Root-Pass-2026!',
            'app_name' => 'Congregation North',
        ])
        ->goToWizardStep(2)
        ->assertWizardCurrentStep(2);

    expect(session(SetupWizard::PROGRESS_SESSION_KEY.'.step'))->toBe(1);

    Livewire::test(SetupWizard::class)
        ->assertWizardCurrentStep(2)
        ->assertSchemaStateSet([
            'root_first_name' => 'Anna',
            'app_name' => 'Congregation North',
            // The passwords are never kept
            'root_password' => null,
        ], 'form');
});

test('the review step shows the choices before installing', function () {
    completeSetupEnvironmentFile($this->envPath);
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm(setupWizardData([
            'administrators' => [
                ['first_name' => 'Marco', 'last_name' => 'Bianchi', 'email' => 'marco@example.com'],
            ],
        ]))
        ->assertSee(__('setup.review.intro'))
        ->assertSee('anna@example.com')
        ->assertSee('Congregation North')
        ->assertSee('marco@example.com')
        ->assertSee(__('setup.review.test_email_not_sent'))
        ->assertSeeHtml('data-enabled="true"')
        // Without a test email, the email confirmation is never required
        ->assertSeeHtml('data-enabled="false"')
        // The links to edit each group bring back to its step
        ->assertSeeHtml(e("step: 'form.".Str::slug(__('setup.steps.root.label'))."::data::wizard-step'"));
});

test('the values of .env reach the page only with the setup token', function () {
    completeSetupEnvironmentFile($this->envPath);
    file_put_contents($this->envPath, str_replace('DB_HOST=set', 'DB_HOST=db.internal.example', file_get_contents($this->envPath)));

    Livewire::test(SetupWizard::class)
        ->assertDontSee('db.internal.example')
        ->set('unlock.token', 'the-setup-token')
        ->call('unlockWizard')
        ->assertSchemaStateSet(['db_host' => 'db.internal.example'], 'form');
});

test('a relative SQLite path is inside the database folder, never in the public one', function () {
    file_put_contents($this->envPath, "SETUP_TOKEN=the-setup-token\nAPP_URL=https://congregation.example.com\nAPP_TIMEZONE=UTC\nAPP_LOCALE=en\nMAIL_MAILER=log\nMAIL_FROM_ADDRESS=noreply@example.com\n");
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm(['db_connection' => 'sqlite', 'db_database' => '../public/och.sqlite'])
        ->goToWizardStep(3)
        ->assertNotified(__('setup.environment.db_sqlite_public'))
        ->assertWizardCurrentStep(2);

    expect(file_exists(public_path('och.sqlite')))->toBeFalse();

    $path = database_path('och-setup-test.sqlite');
    $databaseConfiguration = config('database');

    try {
        Livewire::test(SetupWizard::class)
            ->fillForm(['db_connection' => 'sqlite', 'db_database' => 'och-setup-test.sqlite'])
            ->goToWizardStep(3)
            ->assertWizardCurrentStep(3);

        expect(file_get_contents($this->envPath))->toContain("DB_DATABASE={$path}")
            ->and(file_exists($path))->toBeTrue();
    } finally {
        // The wizard switched the app to this database: back to the one of the tests before removing it
        config(['database' => $databaseConfiguration]);

        if ($databaseConfiguration['default'] !== 'sqlite') {
            DB::purge('sqlite');
        }

        @unlink($path);
    }
});

test('the administrators need a new email address, other than the one of the root user', function () {
    completeSetupEnvironmentFile($this->envPath);
    unlockSetupWizard();
    User::factory()->create(['id' => 2, 'email' => 'taken@example.com']);

    Livewire::test(SetupWizard::class)
        ->fillForm(setupWizardData([
            'administrators' => [
                ['first_name' => 'Marco', 'last_name' => 'Bianchi', 'email' => 'taken@example.com'],
                ['first_name' => 'Anna', 'last_name' => 'Rossi', 'email' => 'anna@example.com'],
            ],
        ]))
        ->call('complete')
        ->assertHasFormErrors(['administrators.0.email' => 'unique', 'administrators.1.email']);

    expect(User::rootUser())->toBeNull();
});

test('the password of the root user follows the password policy', function () {
    completeSetupEnvironmentFile($this->envPath);
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->fillForm(setupWizardData(['root_password' => 'short', 'root_password_confirmation' => 'short']))
        ->call('complete')
        ->assertHasFormErrors(['root_password']);
});

test('the keys of the AI assistants are created by the configuration step', function () {
    $keysPath = sys_get_temp_dir().'/och-setup-keys-'.uniqid();
    mkdir($keysPath);
    Passport::loadKeysFrom($keysPath);

    completeSetupEnvironmentFile($this->envPath);
    unlockSetupWizard();

    Livewire::test(SetupWizard::class)
        ->assertSeeHtml('data-key="passport" data-status="created"')
        ->goToWizardStep(3)
        ->assertWizardCurrentStep(3);

    expect(SetupWizard::hasPassportKeys())->toBeTrue();

    array_map('unlink', glob("{$keysPath}/*"));
    rmdir($keysPath);
    Passport::$keyPath = null;
});

test('the background tasks step saves the queue and tells how to run the tasks', function () {
    completeSetupEnvironmentFile($this->envPath);
    unlockSetupWizard();

    expect(array_keys(SetupWizard::queueOptions()))->toBe(array_keys(config('queue.connections')));

    Livewire::test(SetupWizard::class)
        ->assertSee('artisan schedule:run')
        ->assertSee(url('/api/artisan/schedule/process'))
        ->assertSee('X-Cron-Token: cron-token')
        // With the sync queue there is no worker to run
        ->assertDontSee('artisan queue:work --stop-when-empty')
        ->fillForm(['queue_connection' => 'database'])
        ->assertSee('artisan queue:work --stop-when-empty')
        ->goToWizardStep(4)
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(4);

    expect(file_get_contents($this->envPath))
        ->toContain("\nQUEUE_CONNECTION=database\n")
        ->not->toContain('QUEUE_CONNECTION=sync')
        ->and(config('queue.default'))->toBe('database');
});

test('the scheduler and queue endpoints are turned on only on request', function () {
    unlockSetupWizard();

    $component = Livewire::test(SetupWizard::class)
        ->fillForm(collect(SetupWizard::ENVIRONMENT_VARIABLES)->keys()->mapWithKeys(fn (string $variable): array => [strtolower($variable) => null])->all())
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->assertSee(__('setup.tasks.token_disabled'));

    expect((new EnvironmentFile($this->envPath))->fileValues())->not->toHaveKey('ARTISAN_CRON_TOKEN');

    $component->callAction(TestAction::make('enableCronEndpoints')->schemaComponent('cronActions', 'form'));

    $token = (new EnvironmentFile($this->envPath))->fileValues()['ARTISAN_CRON_TOKEN'] ?? '';

    expect($token)->toHaveLength(64)
        ->and(config('app.artisan_cron_token'))->toBe($token);

    $component->assertSee('X-Cron-Token: '.$token);
});
