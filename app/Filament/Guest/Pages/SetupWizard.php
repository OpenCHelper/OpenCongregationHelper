<?php

namespace App\Filament\Guest\Pages;

use App\Enums\InstallationCheckStatus;
use App\Filament\Clusters\Settings\Pages\ManageGeneralSettings;
use App\Filament\Clusters\Settings\Pages\ManageSecuritySettings;
use App\Models\User;
use App\Notifications\TestEmailNotification;
use App\Notifications\UserInvitationNotification;
use App\Settings\GeneralSettings;
use App\Settings\SecuritySettings;
use App\Settings\TerritorySettings;
use App\Support\EnvironmentFile;
use App\Support\Installation;
use App\Support\InstallationChecks;
use BezhanSalleh\FilamentShield\Support\Utils;
use Database\Seeders\ShieldSeeder;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as Notifications;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * Install the application from the browser, while it is not installed yet (see `Installation`): check the server,
 * fill in the missing values of `.env`, create the database tables and the root user, then choose the name and the
 * appearance of the app, its administrators and its features.
 *
 * It opens only with the `SETUP_TOKEN` of `.env`, so that whoever reaches the site first cannot install it. Once the
 * root user exists the application is installed, and the wizard is gone for good.
 *
 * @property-read Schema $form
 * @property-read Schema $tokenForm
 * @property-read list<array{key: string, check: string, status: InstallationCheckStatus, details: string}> $requirements
 */
class SetupWizard extends Page
{
    protected static ?string $slug = 'setup';

    protected Width|string|null $maxContentWidth = Width::FiveExtraLarge;

    /**
     * Session key of the hash of the token with which the wizard was opened.
     */
    public const UNLOCKED_SESSION_KEY = 'setup_wizard.unlocked';

    /**
     * Attempts allowed per minute at typing the token, from the same address.
     */
    public const TOKEN_ATTEMPTS_PER_MINUTE = 5;

    /**
     * The variables of `.env` that the wizard fills in when they are missing, with the configuration each one sets.
     *
     * @var array<string, string>
     */
    public const ENVIRONMENT_VARIABLES = [
        'APP_URL' => 'app.url',
        'APP_TIMEZONE' => 'app.timezone',
        'APP_LOCALE' => 'app.locale',
        'DB_CONNECTION' => 'database.default',
        'DB_HOST' => 'database.connections.{connection}.host',
        'DB_PORT' => 'database.connections.{connection}.port',
        'DB_DATABASE' => 'database.connections.{connection}.database',
        'DB_USERNAME' => 'database.connections.{connection}.username',
        'DB_PASSWORD' => 'database.connections.{connection}.password',
        'MAIL_MAILER' => 'mail.default',
        'MAIL_HOST' => 'mail.mailers.smtp.host',
        'MAIL_PORT' => 'mail.mailers.smtp.port',
        'MAIL_USERNAME' => 'mail.mailers.smtp.username',
        'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
        'MAIL_FROM_ADDRESS' => 'mail.from.address',
    ];

    /**
     * The database drivers the wizard offers.
     *
     * @var array<string, string>
     */
    public const DATABASE_CONNECTIONS = [
        'mysql' => 'MySQL',
        'mariadb' => 'MariaDB',
        'pgsql' => 'PostgreSQL',
        'sqlite' => 'SQLite',
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $unlock = [];

    /**
     * The variables of `.env` without a value, asked for by the Environment step.
     *
     * @var list<string>
     */
    public array $missingVariables = [];

    /**
     * The address of the page, used while `APP_URL` is not set.
     */
    public string $requestRoot = '';

    /**
     * The address the test email was sent to: requiring the email confirmation needs one.
     */
    public ?string $testEmailSentTo = null;

    public bool $completed = false;

    public static function canAccess(): bool
    {
        return Installation::needsSetup();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return __('setup.title');
    }

    public function getSubheading(): ?string
    {
        return $this->completed ? null : __('setup.subheading');
    }

    public function mount(): void
    {
        $this->requestRoot = request()->root();
        $this->missingVariables = app(EnvironmentFile::class)->missing(array_keys(self::ENVIRONMENT_VARIABLES));

        $settingsTimezone = (string) config('app.timezone');
        [$rootFirstName, $rootLastName] = array_pad(explode(' ', (string) config('app.initial_root_user.name'), 2), 2, '');

        $this->form->fill([
            'app_url' => $this->requestRoot,
            'app_timezone' => in_array($settingsTimezone, timezone_identifiers_list(), true) ? $settingsTimezone : 'UTC',
            'app_locale' => (string) config('app.locale'),
            'db_connection' => 'mysql',
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'mail_mailer' => 'smtp',
            'mail_port' => '587',
            'root_first_name' => $rootFirstName,
            'root_last_name' => $rootLastName,
            'root_email' => config('app.initial_root_user.email'),
            'app_name' => config('app.name'),
            'administrators' => [],
            'ai_assistants_enabled' => SecuritySettings::defaultValue('ai_assistants_enabled'),
            'sharing_enabled' => TerritorySettings::defaultValue('sharing_enabled'),
            'guest_returns' => (bool) TerritorySettings::configuredValue('guest_returns'),
            'require_two_factor' => false,
            'require_email_verification' => false,
        ]);
    }

    /**
     * Whether the wizard was opened with the token of the installation.
     */
    public function isUnlocked(): bool
    {
        $token = (string) config('app.setup.token');

        return $token !== '' && hash_equals(hash('sha256', $token), (string) session(self::UNLOCKED_SESSION_KEY));
    }

    protected function ensureUnlocked(): void
    {
        abort_unless(Installation::needsSetup() && $this->isUnlocked(), 403);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make(__('setup.token.missing_heading'))
                    ->description(fn (): HtmlString => new HtmlString(__('setup.token.missing_description', [
                        'example' => '<code>SETUP_TOKEN='.e(Str::random(32)).'</code>',
                    ])))
                    ->warning()
                    ->visible(fn (): bool => ! $this->completed && blank(config('app.setup.token'))),
                Form::make([EmbeddedSchema::make('tokenForm')])
                    ->id('tokenForm')
                    ->livewireSubmitHandler('unlockWizard')
                    ->footer([
                        Actions::make([
                            Action::make('unlockWizard')
                                ->label(__('setup.token.submit'))
                                ->submit('unlockWizard'),
                        ]),
                    ])
                    ->visible(fn (): bool => ! $this->completed && filled(config('app.setup.token')) && ! $this->isUnlocked()),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('complete')
                    ->visible(fn (): bool => ! $this->completed && $this->isUnlocked()),
                Callout::make(__('setup.completed.heading'))
                    ->description(__('setup.completed.description'))
                    ->success()
                    ->actions([
                        Action::make('logIn')
                            ->label(__('setup.completed.log_in'))
                            ->url(fn (): string => Filament::getPanel('app')->getLoginUrl() ?? '/app/login'),
                    ])
                    ->visible(fn (): bool => $this->completed),
            ]);
    }

    public function tokenForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('unlock')
            ->components([
                Text::make(__('setup.token.description')),
                TextInput::make('token')
                    ->label(__('setup.token.label'))
                    ->password()
                    ->revealable()
                    ->required(),
            ]);
    }

    /**
     * Open the wizard with the `SETUP_TOKEN` of the installation.
     */
    public function unlockWizard(): void
    {
        abort_unless(Installation::needsSetup(), 403);

        $rateLimiterKey = 'setup-token:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimiterKey, self::TOKEN_ATTEMPTS_PER_MINUTE)) {
            $this->addError('unlock.token', __('setup.token.throttled', ['seconds' => RateLimiter::availableIn($rateLimiterKey)]));

            return;
        }

        $token = (string) ($this->tokenForm->getState()['token'] ?? '');

        if (! Installation::tokenMatches($token)) {
            RateLimiter::hit($rateLimiterKey);
            $this->addError('unlock.token', __('setup.token.invalid'));

            return;
        }

        RateLimiter::clear($rateLimiterKey);
        session()->put(self::UNLOCKED_SESSION_KEY, hash('sha256', $token));
        $this->unlock = [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    $this->requirementsStep(),
                    $this->environmentStep(),
                    $this->databaseStep(),
                    $this->rootUserStep(),
                    $this->congregationStep(),
                    $this->administratorsStep(),
                    $this->featuresStep(),
                ])
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" icon="heroicon-o-check">{{ $label }}</x-filament::button>',
                        ['label' => __('setup.complete')],
                    ))),
            ]);
    }

    protected function requirementsStep(): Step
    {
        return Step::make('requirements')
            ->label(__('setup.steps.requirements.label'))
            ->description(__('setup.steps.requirements.description'))
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->schema([
                View::make('filament.setup.installation-checks')
                    ->viewData(fn (): array => ['checks' => $this->requirements]),
                Actions::make([
                    Action::make('checkAgain')
                        ->label(__('setup.checks.check_again'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('gray')
                        ->action(function (): void {
                            unset($this->requirements);
                        }),
                ])->key('requirementsActions'),
            ])
            ->afterValidation(function (): void {
                $this->ensureUnlocked();
                unset($this->requirements);

                if (InstallationChecks::hasErrors($this->requirements)) {
                    $this->stopStep(__('setup.checks.errors'));
                }
            });
    }

    protected function environmentStep(): Step
    {
        $missing = fn (string ...$variables): bool => array_intersect($variables, $this->missingVariables) !== [];
        $databaseConnection = fn (Get $get): string => $missing('DB_CONNECTION') ? (string) $get('db_connection') : (string) config('database.default');
        $mailer = fn (Get $get): string => $missing('MAIL_MAILER') ? (string) $get('mail_mailer') : (string) config('mail.default');

        return Step::make('environment')
            ->label(__('setup.steps.environment.label'))
            ->description(__('setup.steps.environment.description'))
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->schema([
                Text::make(fn (): string => $this->missingVariables === []
                    ? __('setup.environment.nothing_missing')
                    : __('setup.environment.intro')),
                Callout::make(__('setup.environment.not_writable'))
                    ->warning()
                    ->visible(fn (): bool => $this->missingVariables !== [] && ! app(EnvironmentFile::class)->isWritable()),
                Section::make(__('setup.environment.application'))
                    ->columns(['default' => 1, 'md' => 3])
                    ->visible(fn (): bool => $missing('APP_URL', 'APP_TIMEZONE', 'APP_LOCALE'))
                    ->schema([
                        TextInput::make('app_url')
                            ->label('APP_URL')
                            ->validationAttribute('APP_URL')
                            ->helperText(__('setup.environment.app_url'))
                            ->url()
                            ->required()
                            ->visible(fn (): bool => $missing('APP_URL')),
                        Select::make('app_timezone')
                            ->label('APP_TIMEZONE')
                            ->validationAttribute('APP_TIMEZONE')
                            ->helperText(__('setup.environment.app_timezone'))
                            ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                            ->searchable()
                            ->required()
                            ->visible(fn (): bool => $missing('APP_TIMEZONE')),
                        Select::make('app_locale')
                            ->label('APP_LOCALE')
                            ->validationAttribute('APP_LOCALE')
                            ->helperText(__('setup.environment.app_locale'))
                            ->options(fn (): array => config('app.available_locales'))
                            ->required()
                            ->visible(fn (): bool => $missing('APP_LOCALE')),
                    ]),
                Section::make(__('setup.environment.database'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible(fn (): bool => $missing('DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'))
                    ->schema([
                        Select::make('db_connection')
                            ->label('DB_CONNECTION')
                            ->validationAttribute('DB_CONNECTION')
                            ->options(self::DATABASE_CONNECTIONS)
                            ->required()
                            ->live()
                            ->visible(fn (): bool => $missing('DB_CONNECTION')),
                        TextInput::make('db_database')
                            ->label('DB_DATABASE')
                            ->validationAttribute('DB_DATABASE')
                            ->helperText(fn (Get $get): ?string => $databaseConnection($get) === 'sqlite' ? __('setup.environment.db_sqlite') : null)
                            ->required()
                            ->visible(fn (): bool => $missing('DB_DATABASE')),
                        TextInput::make('db_host')
                            ->label('DB_HOST')
                            ->validationAttribute('DB_HOST')
                            ->required()
                            ->visible(fn (Get $get): bool => $missing('DB_HOST') && $databaseConnection($get) !== 'sqlite'),
                        TextInput::make('db_port')
                            ->label('DB_PORT')
                            ->validationAttribute('DB_PORT')
                            ->integer()
                            ->required()
                            ->visible(fn (Get $get): bool => $missing('DB_PORT') && $databaseConnection($get) !== 'sqlite'),
                        TextInput::make('db_username')
                            ->label('DB_USERNAME')
                            ->validationAttribute('DB_USERNAME')
                            ->required()
                            ->visible(fn (Get $get): bool => $missing('DB_USERNAME') && $databaseConnection($get) !== 'sqlite'),
                        TextInput::make('db_password')
                            ->label('DB_PASSWORD')
                            ->validationAttribute('DB_PASSWORD')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get): bool => $missing('DB_PASSWORD') && $databaseConnection($get) !== 'sqlite'),
                    ]),
                Section::make(__('setup.environment.mail'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->visible(fn (): bool => $missing('MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'))
                    ->schema([
                        Select::make('mail_mailer')
                            ->label('MAIL_MAILER')
                            ->validationAttribute('MAIL_MAILER')
                            ->options([
                                'smtp' => 'SMTP',
                                'sendmail' => 'Sendmail',
                                'nativemailfx' => __('setup.environment.mail_native'),
                                'log' => __('setup.environment.mail_log'),
                            ])
                            ->required()
                            ->live()
                            ->visible(fn (): bool => $missing('MAIL_MAILER')),
                        TextInput::make('mail_from_address')
                            ->label('MAIL_FROM_ADDRESS')
                            ->validationAttribute('MAIL_FROM_ADDRESS')
                            ->helperText(__('setup.environment.mail_from_address'))
                            ->email()
                            ->required()
                            ->visible(fn (): bool => $missing('MAIL_FROM_ADDRESS')),
                        TextInput::make('mail_host')
                            ->label('MAIL_HOST')
                            ->validationAttribute('MAIL_HOST')
                            ->required()
                            ->visible(fn (Get $get): bool => $missing('MAIL_HOST') && $mailer($get) === 'smtp'),
                        TextInput::make('mail_port')
                            ->label('MAIL_PORT')
                            ->validationAttribute('MAIL_PORT')
                            ->integer()
                            ->required()
                            ->visible(fn (Get $get): bool => $missing('MAIL_PORT') && $mailer($get) === 'smtp'),
                        TextInput::make('mail_username')
                            ->label('MAIL_USERNAME')
                            ->validationAttribute('MAIL_USERNAME')
                            ->visible(fn (Get $get): bool => $missing('MAIL_USERNAME') && $mailer($get) === 'smtp'),
                        TextInput::make('mail_password')
                            ->label('MAIL_PASSWORD')
                            ->validationAttribute('MAIL_PASSWORD')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get): bool => $missing('MAIL_PASSWORD') && $mailer($get) === 'smtp'),
                    ]),
            ])
            ->afterValidation(function (): void {
                $this->ensureUnlocked();
                $this->saveEnvironment();
            });
    }

    protected function databaseStep(): Step
    {
        return Step::make('database')
            ->label(__('setup.steps.database.label'))
            ->description(__('setup.steps.database.description'))
            ->icon(Heroicon::OutlinedCircleStack)
            ->schema([
                Text::make(__('setup.database.intro')),
            ])
            ->afterValidation(function (): void {
                $this->ensureUnlocked();
                $this->setUpDatabase();
            });
    }

    protected function rootUserStep(): Step
    {
        return Step::make('root')
            ->label(__('setup.steps.root.label'))
            ->description(__('setup.steps.root.description'))
            ->icon(Heroicon::OutlinedUserCircle)
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Text::make(__('setup.root.intro'))->columnSpanFull(),
                TextInput::make('root_first_name')
                    ->label(__('filament-resources.user.field_first_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('root_last_name')
                    ->label(__('filament-resources.user.field_last_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('root_email')
                    ->label(__('filament-resources.user.field_email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->columnSpanFull(),
                TextInput::make('root_password')
                    ->label(__('setup.root.password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(fn (): Password => Password::default())
                    ->same('root_password_confirmation'),
                TextInput::make('root_password_confirmation')
                    ->label(__('setup.root.password_confirmation'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
                Actions::make([
                    Action::make('sendTestEmail')
                        ->label(__('setup.checks.test_email.label'))
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->color('gray')
                        ->action(fn () => $this->sendTestEmail()),
                ])->key('rootActions')->columnSpanFull(),
                Text::make(fn (): string => $this->testEmailSentTo !== null
                    ? __('setup.checks.test_email.sent', ['email' => $this->testEmailSentTo])
                    : __('setup.root.test_email_hint'))
                    ->columnSpanFull(),
            ]);
    }

    protected function congregationStep(): Step
    {
        return Step::make('congregation')
            ->label(__('setup.steps.congregation.label'))
            ->description(__('setup.steps.congregation.description'))
            ->icon(Heroicon::OutlinedHome)
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                ManageGeneralSettings::appNameField()->columnSpanFull(),
                ManageGeneralSettings::kingdomHallAddressField()->columnSpanFull(),
                ManageGeneralSettings::logoField(),
                ManageGeneralSettings::primaryColorField(),
            ]);
    }

    protected function administratorsStep(): Step
    {
        return Step::make('administrators')
            ->label(__('setup.steps.administrators.label'))
            ->description(__('setup.steps.administrators.description'))
            ->icon(Heroicon::OutlinedUserGroup)
            ->schema([
                Text::make(__('setup.administrators.intro')),
                Repeater::make('administrators')
                    ->hiddenLabel()
                    ->defaultItems(0)
                    ->addActionLabel(__('setup.administrators.add'))
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('filament-resources.user.field_first_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label(__('filament-resources.user.field_last_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('filament-resources.user.field_email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->distinct()
                            ->notIn(fn (): array => array_filter([(string) ($this->data['root_email'] ?? '')]))
                            ->validationMessages(['not_in' => __('setup.administrators.root_email')])
                            ->unique(User::class, 'email'),
                    ]),
            ]);
    }

    protected function featuresStep(): Step
    {
        return Step::make('features')
            ->label(__('setup.steps.features.label'))
            ->description(__('setup.steps.features.description'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->schema([
                Section::make(__('settings.sections.ai_assistants'))
                    ->description(__('settings.sections.ai_assistants_description'))
                    ->schema([
                        ManageSecuritySettings::aiAssistantsEnabledField(),
                    ]),
                Section::make(__('setup.features.sharing'))
                    ->description(__('setup.features.sharing_description'))
                    ->schema([
                        Toggle::make('sharing_enabled')
                            ->label(__('settings.fields.sharing_enabled.label'))
                            ->helperText(__('settings.fields.sharing_enabled.helper'))
                            ->live(),
                        Toggle::make('guest_returns')
                            ->visible(fn (Get $get): bool => (bool) $get('sharing_enabled'))
                            ->dehydratedWhenHidden()
                            ->label(__('settings.fields.guest_returns.label'))
                            ->helperText(__('settings.fields.guest_returns.helper')),
                    ]),
                Section::make(__('settings.sections.account_requirements'))
                    ->description(__('settings.sections.account_requirements_description'))
                    ->schema([
                        Toggle::make('require_two_factor')
                            ->label(__('settings.fields.require_two_factor.label'))
                            ->helperText(__('settings.fields.require_two_factor.helper').' '.__('setup.features.two_factor_root')),
                        Toggle::make('require_email_verification')
                            ->label(__('settings.fields.require_email_verification.label'))
                            ->helperText(fn (): string => __('settings.fields.require_email_verification.helper').' '.(
                                $this->testEmailSentTo !== null ? '' : __('setup.features.email_verification_needs_test')
                            ))
                            ->disabled(fn (): bool => $this->testEmailSentTo === null),
                    ]),
            ]);
    }

    /**
     * The requirements of the server, checked again by "Check again" and before going on.
     *
     * @return list<array{key: string, check: string, status: InstallationCheckStatus, details: string}>
     */
    #[Computed]
    public function requirements(): array
    {
        $url = in_array('APP_URL', $this->missingVariables, true) ? $this->requestRoot : (string) config('app.url');

        return app(InstallationChecks::class)->requirements($url);
    }

    /**
     * Check the database with the values typed, then write the missing values in `.env`.
     */
    protected function saveEnvironment(): void
    {
        $isMissing = fn (string $variable): bool => in_array($variable, $this->missingVariables, true);
        $connection = $isMissing('DB_CONNECTION') ? (string) ($this->data['db_connection'] ?? '') : (string) config('database.default');
        $mailer = $isMissing('MAIL_MAILER') ? (string) ($this->data['mail_mailer'] ?? '') : (string) config('mail.default');

        $values = collect([
            'APP_URL' => rtrim((string) ($this->data['app_url'] ?? ''), '/'),
            'APP_TIMEZONE' => $this->data['app_timezone'] ?? null,
            'APP_LOCALE' => $this->data['app_locale'] ?? null,
            'DB_CONNECTION' => $this->data['db_connection'] ?? null,
            'DB_HOST' => $this->data['db_host'] ?? null,
            'DB_PORT' => $this->data['db_port'] ?? null,
            'DB_DATABASE' => $this->data['db_database'] ?? null,
            'DB_USERNAME' => $this->data['db_username'] ?? null,
            'DB_PASSWORD' => $this->data['db_password'] ?? null,
            'MAIL_MAILER' => $this->data['mail_mailer'] ?? null,
            'MAIL_HOST' => $this->data['mail_host'] ?? null,
            'MAIL_PORT' => $this->data['mail_port'] ?? null,
            'MAIL_USERNAME' => $this->data['mail_username'] ?? null,
            'MAIL_PASSWORD' => $this->data['mail_password'] ?? null,
            'MAIL_FROM_ADDRESS' => $this->data['mail_from_address'] ?? null,
        ])
            ->only($this->missingVariables)
            // The fields hidden for the chosen database and mailer keep their default value: it is not written
            ->except($connection === 'sqlite' ? ['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] : [])
            ->except($mailer !== 'smtp' ? ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD'] : [])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->all();

        $this->checkDatabase($connection, $values);
        $this->applyToConfiguration($values);

        try {
            app(EnvironmentFile::class)->writeMissing($values);
        } catch (Throwable $exception) {
            $this->stopStep(__('setup.environment.write_failed'), $exception->getMessage());
        }

        // A cached configuration would hide the new values from the next requests
        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        Installation::forget();
        $this->missingVariables = app(EnvironmentFile::class)->missing(array_keys(self::ENVIRONMENT_VARIABLES));
    }

    /**
     * Check that the database can be reached with the values typed, on a connection of its own, so that the current one
     * is left as it is when it cannot.
     *
     * @param  array<string, string>  $values
     */
    protected function checkDatabase(string $connection, array $values): void
    {
        $settings = (array) config("database.connections.{$connection}");

        foreach ($values as $variable => $value) {
            $key = self::ENVIRONMENT_VARIABLES[$variable];

            if (str_starts_with($key, 'database.connections.')) {
                $settings[str($key)->afterLast('.')->toString()] = $value;
            }
        }

        try {
            if (($settings['driver'] ?? null) === 'sqlite') {
                $this->createSqliteDatabase((string) ($settings['database'] ?? ''));
            }

            config(['database.connections.setup_check' => $settings]);
            DB::connection('setup_check')->getPdo();
        } catch (Throwable $exception) {
            $this->stopStep(__('setup.environment.database_failed'), $exception->getMessage());
        } finally {
            DB::purge('setup_check');
        }
    }

    /**
     * Use the values typed for the rest of this request, as the next ones will read them from `.env`.
     *
     * @param  array<string, string>  $values
     */
    protected function applyToConfiguration(array $values): void
    {
        $connection = $values['DB_CONNECTION'] ?? (string) config('database.default');

        foreach ($values as $variable => $value) {
            config([str_replace('{connection}', $connection, self::ENVIRONMENT_VARIABLES[$variable]) => $value]);
        }
    }

    protected function createSqliteDatabase(string $path): void
    {
        if ($path !== ':memory:' && ! file_exists($path)) {
            @touch($path);
        }
    }

    /**
     * Create the tables, the permissions of the roles and the keys of the AI assistants, as `och:install` does.
     */
    protected function setUpDatabase(): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $steps = [
            ['migrate', ['--force' => true]],
            ['db:seed', ['--class' => ShieldSeeder::class, '--force' => true]],
            ['och:ensure-passport-keys', []],
        ];

        foreach ($steps as [$command, $arguments]) {
            try {
                $exitCode = Artisan::call($command, $arguments);
            } catch (Throwable $exception) {
                report($exception);
                $this->stopStep(__('setup.database.failed', ['command' => "php artisan {$command}"]), $exception->getMessage());
            }

            if ($exitCode !== 0) {
                $this->stopStep(__('setup.database.failed', ['command' => "php artisan {$command}"]), trim(Artisan::output()));
            }
        }

        // The web server may not follow the link (e.g. Altervista), where the files are served by the application
        try {
            Artisan::call('storage:link', ['--force' => true]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Send an email to the root user, to check that the emails reach their recipients.
     */
    protected function sendTestEmail(): void
    {
        $this->ensureUnlocked();

        $email = trim((string) ($this->data['root_email'] ?? ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Notification::make()->danger()->title(__('setup.root.email_first'))->send();

            return;
        }

        $name = trim(($this->data['root_first_name'] ?? '').' '.($this->data['root_last_name'] ?? ''));

        try {
            // Right away, also when the notifications are queued, so that the errors are shown here
            Notifications::route('mail', $email)->notifyNow(new TestEmailNotification($name));
        } catch (Throwable $exception) {
            report($exception);
            $this->testEmailSentTo = null;

            Notification::make()
                ->danger()
                ->title(__('setup.checks.test_email.failed'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        $this->testEmailSentTo = $email;

        Notification::make()
            ->success()
            ->title(__('setup.checks.test_email.sent', ['email' => $email]))
            ->body(__('setup.checks.test_email.sent_body'))
            ->send();
    }

    /**
     * Create the root user and the administrators, and save the settings: the application is installed.
     */
    public function complete(): void
    {
        $this->ensureUnlocked();

        $data = $this->form->getState();

        // Changing the address after the test email takes the confirmation of the emails off
        $requireEmailVerification = (bool) ($data['require_email_verification'] ?? false)
            && $this->testEmailSentTo !== null
            && $this->testEmailSentTo === $data['root_email'];

        $administrators = DB::transaction(function () use ($data, $requireEmailVerification): array {
            $role = Utils::createRole();

            // The ID is set: the root user is the one with ID 1. Its email is confirmed: it was typed by whoever has the
            // token of the installation, and it must never lock the root user out.
            $root = User::forceCreate([
                'id' => 1,
                'first_name' => $data['root_first_name'],
                'last_name' => $data['root_last_name'],
                'email' => $data['root_email'],
                'password' => $data['root_password'],
                'can_login' => true,
                'email_verified_at' => now(),
            ]);
            $root->assignRole($role);

            app(GeneralSettings::class)->fill([
                'app_name' => trim((string) $data['app_name']),
                'kingdom_hall_address' => $data['kingdom_hall_address'] ?? null,
                'logo' => $data['logo'] ?? null,
                'primary_color' => $data['primary_color'] ?? null,
            ])->save();

            app(SecuritySettings::class)->fill([
                'ai_assistants_enabled' => (bool) $data['ai_assistants_enabled'],
                'require_two_factor' => (bool) $data['require_two_factor'],
                'require_email_verification' => $requireEmailVerification,
            ])->save();

            app(TerritorySettings::class)->fill([
                'sharing_enabled' => (bool) $data['sharing_enabled'],
                'guest_returns' => (bool) $data['guest_returns'],
            ])->save();

            return collect($data['administrators'] ?? [])
                ->map(function (array $administrator) use ($role): User {
                    $user = User::create([
                        'first_name' => $administrator['first_name'],
                        'last_name' => $administrator['last_name'],
                        'email' => $administrator['email'],
                        'can_login' => true,
                    ]);

                    $user->assignRole($role);

                    return $user;
                })
                ->all();
        });

        Installation::markInstalled();

        foreach ($administrators as $administrator) {
            try {
                UserInvitationNotification::sendTo($administrator);
            } catch (Throwable $exception) {
                // The invitation can be sent again from the users, once the emails work
                report($exception);
            }
        }

        session()->forget(self::UNLOCKED_SESSION_KEY);
        $this->completed = true;
    }

    /**
     * Stop on the current step, telling what went wrong.
     */
    protected function stopStep(string $title, ?string $body = null): never
    {
        Notification::make()
            ->danger()
            ->title($title)
            ->body($body)
            ->persistent()
            ->send();

        throw new Halt;
    }
}
