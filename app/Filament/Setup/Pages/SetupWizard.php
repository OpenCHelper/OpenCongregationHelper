<?php

namespace App\Filament\Setup\Pages;

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
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification as Notifications;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use Livewire\Attributes\Computed;
use Minishlink\WebPush\VAPID;
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

    /**
     * The wizard is the root of its own panel (`/setup`, see `SetupPanelProvider`).
     */
    #[Unexposed]
    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

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
        'DB_PREFIX' => 'database.connections.{connection}.prefix',
        'MAIL_MAILER' => 'mail.default',
        'MAIL_HOST' => 'mail.mailers.smtp.host',
        'MAIL_PORT' => 'mail.mailers.smtp.port',
        'MAIL_USERNAME' => 'mail.mailers.smtp.username',
        'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
        'MAIL_FROM_ADDRESS' => 'mail.from.address',
        'QUEUE_CONNECTION' => 'queue.default',
    ];

    /**
     * The variables of `.env` asked for by the Background tasks step instead of the Configuration step.
     *
     * @var list<string>
     */
    public const TASKS_VARIABLES = ['QUEUE_CONNECTION'];

    /**
     * The names of the mailers sending through a service, for the others see `setup.environment.mailers`.
     *
     * @var array<string, string>
     */
    public const MAILER_NAMES = [
        'smtp' => 'SMTP',
        'ses' => 'Amazon SES',
        'postmark' => 'Postmark',
        'resend' => 'Resend',
        'sendmail' => 'Sendmail',
    ];

    /**
     * The mailers that keep the emails instead of sending them.
     *
     * @var list<string>
     */
    public const NOT_SENDING_MAILERS = ['log', 'array'];

    /**
     * The keys of the push notifications, created by the Configuration step when both are missing.
     *
     * @var list<string>
     */
    public const VAPID_VARIABLES = ['VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY'];

    /**
     * The variables of `.env` that the wizard can also change when they are already set (in `.env`, not in the
     * environment of the server, which comes first): the database, and the mailer so that the test email can be sent
     * another way. A password left empty is kept.
     *
     * @var list<string>
     */
    public const CHANGEABLE_VARIABLES = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_PREFIX', 'MAIL_MAILER', 'QUEUE_CONNECTION'];

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
     * The variables already set in `.env` that the Configuration step can change (see `CHANGEABLE_VARIABLES`).
     *
     * @var list<string>
     */
    public array $changeableVariables = [];

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

    /**
     * The heading of the wizard: before it is unlocked and once it is completed, the page shows a card with its own.
     */
    public function getHeading(): ?string
    {
        return $this->showsCard() ? null : $this->getTitle();
    }

    public function getSubheading(): ?string
    {
        return $this->showsCard() ? null : __('setup.subheading').' '.__('setup.changeable_later');
    }

    /**
     * Whether the page shows a single card (to add or type the token, or once installed) instead of the wizard.
     */
    protected function showsCard(): bool
    {
        return $this->completed || ! $this->isUnlocked();
    }

    public function mount(): void
    {
        $this->requestRoot = request()->root();
        $this->refreshEnvironmentVariables();

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
            ...$this->configuredEnvironmentValues(),
        ]);
    }

    /**
     * The current value of a variable: the one written in `.env`, or else the one of the environment of the server.
     */
    protected function currentEnvironmentValue(string $variable): string
    {
        return (string) (app(EnvironmentFile::class)->fileValues()[$variable] ?? Env::get($variable));
    }

    /**
     * The values of the variables of `.env` that are already set, shown by the Configuration step (editable only for
     * the ones that can be changed), keyed by their field. The passwords are left out: they never reach the browser.
     *
     * @return array<string, mixed>
     */
    protected function configuredEnvironmentValues(): array
    {
        return collect(array_keys(self::ENVIRONMENT_VARIABLES))
            ->diff([...$this->missingVariables, 'DB_PASSWORD', 'MAIL_PASSWORD'])
            ->mapWithKeys(fn (string $variable): array => [strtolower($variable) => $this->currentEnvironmentValue($variable)])
            ->all();
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
                Group::make([
                    Section::make(fn (): string => match (true) {
                        $this->completed => __('setup.completed.heading'),
                        $this->isMissingToken() => __('setup.token.missing_heading'),
                        default => __('setup.title'),
                    })
                        ->description(fn (): string|HtmlString => match (true) {
                            $this->completed => __('setup.completed.description'),
                            $this->isMissingToken() => new HtmlString(__('setup.token.missing_description', [
                                'example' => '<code>SETUP_TOKEN='.e(Str::random(32)).'</code>',
                            ])),
                            default => __('setup.token.description'),
                        })
                        ->icon(fn (): Heroicon => match (true) {
                            $this->completed => Heroicon::OutlinedCheckCircle,
                            $this->isMissingToken() => Heroicon::OutlinedExclamationTriangle,
                            default => Heroicon::OutlinedKey,
                        })
                        ->iconColor(fn (): string => match (true) {
                            $this->completed => 'success',
                            $this->isMissingToken() => 'warning',
                            default => 'primary',
                        })
                        ->schema([
                            Form::make([EmbeddedSchema::make('tokenForm')])
                                ->id('tokenForm')
                                ->livewireSubmitHandler('unlockWizard')
                                ->footer([
                                    Actions::make([
                                        Action::make('unlockWizard')
                                            ->label(__('setup.token.submit'))
                                            ->submit('unlockWizard'),
                                    ])->fullWidth(),
                                ])
                                ->visible(fn (): bool => ! $this->completed && ! $this->isMissingToken()),
                            Actions::make([
                                Action::make('logIn')
                                    ->label(__('setup.completed.log_in'))
                                    ->url(fn (): string => Filament::getPanel('app')->getLoginUrl() ?? '/app/login'),
                            ])
                                ->fullWidth()
                                ->visible(fn (): bool => $this->completed),
                        ]),
                    Text::make(__('setup.changeable_later'))
                        ->size(TextSize::ExtraSmall)
                        ->color('gray')
                        ->extraAttributes(['class' => 'block text-center'])
                        ->visible(fn (): bool => ! $this->completed),
                ])
                    ->extraAttributes(['class' => 'mx-auto flex min-h-[calc(100dvh-12rem)] w-full max-w-md flex-col justify-center'])
                    ->visible(fn (): bool => $this->showsCard()),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('complete')
                    ->visible(fn (): bool => ! $this->showsCard()),
            ]);
    }

    /**
     * Whether `SETUP_TOKEN` is not set yet, so that the wizard cannot be opened.
     */
    protected function isMissingToken(): bool
    {
        return blank(config('app.setup.token'));
    }

    public function tokenForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('unlock')
            ->components([
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
                    $this->tasksStep(),
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
                Section::make(__('setup.steps.requirements.description'))
                    ->icon(Heroicon::OutlinedServer)
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
                    ]),
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
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->columns(['default' => 1, 'md' => 3])
                    ->visible(fn (): bool => $missing('APP_URL', 'APP_TIMEZONE', 'APP_LOCALE'))
                    ->schema([
                        $this->environmentField(TextInput::make('app_url')
                            ->helperText(__('setup.environment.app_url'))
                            ->url()
                            ->visible(fn (): bool => $missing('APP_URL')), 'APP_URL', required: true),
                        $this->environmentField(Select::make('app_timezone')
                            ->helperText(__('setup.environment.app_timezone'))
                            ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                            ->searchable()
                            ->visible(fn (): bool => $missing('APP_TIMEZONE')), 'APP_TIMEZONE', required: true),
                        $this->environmentField(Select::make('app_locale')
                            ->helperText(__('setup.environment.app_locale'))
                            ->options(fn (): array => config('app.available_locales'))
                            ->visible(fn (): bool => $missing('APP_LOCALE')), 'APP_LOCALE', required: true),
                    ]),
                Section::make(__('setup.environment.database'))
                    ->icon(Heroicon::OutlinedCircleStack)
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        $this->environmentField(Select::make('db_connection')
                            ->options(self::DATABASE_CONNECTIONS)
                            ->live(), 'DB_CONNECTION', required: true),
                        $this->environmentField(TextInput::make('db_database')
                            ->helperText(fn (Get $get): ?string => self::chosenConnection($get) === 'sqlite' ? __('setup.environment.db_sqlite') : null), 'DB_DATABASE', required: true),
                        $this->environmentField(TextInput::make('db_host')
                            ->visible(fn (Get $get): bool => self::chosenConnection($get) !== 'sqlite'), 'DB_HOST', required: true),
                        $this->environmentField(TextInput::make('db_port')
                            ->integer()
                            ->visible(fn (Get $get): bool => self::chosenConnection($get) !== 'sqlite'), 'DB_PORT', required: true),
                        $this->environmentField(TextInput::make('db_username')
                            ->visible(fn (Get $get): bool => self::chosenConnection($get) !== 'sqlite'), 'DB_USERNAME', required: true),
                        $this->environmentField(TextInput::make('db_password')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get): bool => self::chosenConnection($get) !== 'sqlite'), 'DB_PASSWORD'),
                        $this->environmentField(TextInput::make('db_prefix')
                            ->helperText(__('setup.environment.db_prefix'))
                            ->maxLength(32)
                            ->regex('/^[A-Za-z0-9_]*$/'), 'DB_PREFIX'),
                    ]),
                Section::make(__('setup.environment.mail'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        $this->environmentField(Select::make('mail_mailer')
                            ->options(fn (): array => self::mailerOptions())
                            ->live(), 'MAIL_MAILER', required: true),
                        $this->environmentField(TextInput::make('mail_from_address')
                            ->helperText(__('setup.environment.mail_from_address'))
                            ->email(), 'MAIL_FROM_ADDRESS', required: true),
                        $this->environmentField(TextInput::make('mail_host')
                            ->visible(fn (Get $get): bool => self::chosenMailer($get) === 'smtp'), 'MAIL_HOST', required: true),
                        $this->environmentField(TextInput::make('mail_port')
                            ->integer()
                            ->visible(fn (Get $get): bool => self::chosenMailer($get) === 'smtp'), 'MAIL_PORT', required: true),
                        $this->environmentField(TextInput::make('mail_username')
                            ->visible(fn (Get $get): bool => self::chosenMailer($get) === 'smtp'), 'MAIL_USERNAME'),
                        $this->environmentField(TextInput::make('mail_password')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get): bool => self::chosenMailer($get) === 'smtp'), 'MAIL_PASSWORD'),
                    ]),
                Section::make(__('setup.environment.keys'))
                    ->icon(Heroicon::OutlinedKey)
                    ->schema([
                        View::make('filament.setup.keys')
                            ->viewData(fn (): array => ['keys' => $this->keysStatus()]),
                    ]),
            ])
            ->afterValidation(function (): void {
                $this->ensureUnlocked();
                $this->saveEnvironment();
            });
    }

    protected function tasksStep(): Step
    {
        return Step::make('tasks')
            ->label(__('setup.steps.tasks.label'))
            ->description(__('setup.steps.tasks.description'))
            ->icon(Heroicon::OutlinedClock)
            ->schema([
                Text::make(__('setup.tasks.intro')),
                $this->environmentField(Select::make('queue_connection')
                    ->prefixIcon(Heroicon::OutlinedQueueList)
                    ->helperText(__('setup.tasks.queue_helper'))
                    ->options(fn (): array => self::queueOptions())
                    ->live(), 'QUEUE_CONNECTION', required: true),
                Tabs::make('tasks')
                    ->tabs([
                        Tab::make(__('setup.tasks.with_shell'))
                            ->icon(Heroicon::OutlinedCommandLine)
                            ->schema([
                                View::make('filament.setup.tasks-with-shell')
                                    ->viewData(fn (Get $get): array => [
                                        'scheduler' => 'cd '.base_path().' && '.PHP_BINARY.' artisan schedule:run >> /dev/null 2>&1',
                                        'worker' => self::chosenQueue($get) === 'sync'
                                            ? null
                                            : 'cd '.base_path().' && '.PHP_BINARY.' artisan queue:work --stop-when-empty >> /dev/null 2>&1',
                                    ]),
                            ]),
                        Tab::make(__('setup.tasks.without_shell'))
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->schema([
                                View::make('filament.setup.tasks-without-shell')
                                    ->viewData(fn (Get $get): array => [
                                        'schedulerUrl' => $this->applicationUrl().'/api/artisan/schedule/process',
                                        'queueUrl' => self::chosenQueue($get) === 'sync' ? null : $this->applicationUrl().'/api/artisan/queue/process',
                                        'token' => $this->currentEnvironmentValue('ARTISAN_CRON_TOKEN'),
                                    ]),
                            ]),
                    ]),
            ])
            ->afterValidation(function (): void {
                $this->ensureUnlocked();
                $this->saveTasks();
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
                Callout::make(__('setup.root.mailer_not_sending', ['mailer' => (string) config('mail.default')]))
                    ->warning()
                    ->visible(fn (): bool => self::mailerDoesNotSend())
                    ->columnSpanFull(),
                TextInput::make('root_first_name')
                    ->prefixIcon(Heroicon::OutlinedUser)
                    ->label(__('filament-resources.user.field_first_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('root_last_name')
                    ->prefixIcon(Heroicon::OutlinedUser)
                    ->label(__('filament-resources.user.field_last_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('root_email')
                    ->prefixIcon(Heroicon::OutlinedEnvelope)
                    ->label(__('filament-resources.user.field_email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->columnSpanFull(),
                TextInput::make('root_password')
                    ->prefixIcon(Heroicon::OutlinedLockClosed)
                    ->label(__('setup.root.password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(fn (): Password => Password::default())
                    ->same('root_password_confirmation'),
                TextInput::make('root_password_confirmation')
                    ->prefixIcon(Heroicon::OutlinedLockClosed)
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
                Section::make(__('settings.sections.congregation'))
                    ->icon(Heroicon::OutlinedHome)
                    ->columnSpanFull()
                    ->schema([
                        ManageGeneralSettings::appNameField()->prefixIcon(Heroicon::OutlinedTag),
                        ManageGeneralSettings::kingdomHallAddressField(),
                    ]),
                Section::make(__('settings.sections.appearance'))
                    ->icon(Heroicon::OutlinedPaintBrush)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        ManageGeneralSettings::logoField(),
                        ManageGeneralSettings::primaryColorField(),
                    ]),
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
                            ->prefixIcon(Heroicon::OutlinedUser)
                            ->label(__('filament-resources.user.field_first_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->prefixIcon(Heroicon::OutlinedUser)
                            ->label(__('filament-resources.user.field_last_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->prefixIcon(Heroicon::OutlinedEnvelope)
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
                    ->icon(Heroicon::OutlinedSparkles)
                    ->description(__('settings.sections.ai_assistants_description'))
                    ->schema([
                        ManageSecuritySettings::aiAssistantsEnabledField(),
                    ]),
                Section::make(__('setup.features.sharing'))
                    ->icon(Heroicon::OutlinedShare)
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
                    ->icon(Heroicon::OutlinedShieldCheck)
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
     * A field of a variable of `.env`, editable only while the variable is missing: otherwise it shows the value already
     * set, which the wizard never changes (only that a password is set, never the password itself).
     *
     * @template TField of TextInput|Select
     *
     * @param  TField  $field
     * @return TField
     */
    protected function environmentField(TextInput|Select $field, string $variable, bool $required = false): TextInput|Select
    {
        $isEditable = fn (): bool => $this->isEditableVariable($variable);
        $fallback = fn (Get $get): ?string => $this->environmentFallback($variable, $get);

        $field
            ->label($variable)
            ->validationAttribute($variable)
            ->disabled(fn (): bool => ! $isEditable())
            ->dehydrated($isEditable)
            ->hint(fn (): ?string => $isEditable() ? null : __('setup.environment.already_set'))
            // Left empty, a variable with a fallback in the configuration is not written: the fallback applies
            ->required(fn (Get $get): bool => $required && $isEditable() && blank($fallback($get)))
            ->placeholder(fn (Get $get): ?string => match (true) {
                ! $isEditable() && $field instanceof TextInput && $field->isPassword() => __('setup.environment.password_set'),
                $field instanceof TextInput && $field->isPassword() && in_array($variable, $this->changeableVariables, true) => __('setup.environment.password_keep'),
                $isEditable() && $field instanceof TextInput && $field->isPassword() => null,
                // A select tells its default apart from its options
                $isEditable() && $field instanceof Select && filled($fallback($get)) => __('setup.environment.default_option', [
                    'value' => $field->getOptions()[$fallback($get)] ?? $fallback($get),
                ]),
                $isEditable() => $fallback($get),
                default => null,
            });

        if ($field instanceof TextInput && $field->isPassword()) {
            $field->revealable($isEditable);
        }

        return $field;
    }

    /**
     * The database connection and the mailer of the Configuration step: when left empty, the ones of the configuration.
     */
    protected static function chosenConnection(Get $get): string
    {
        return (string) ($get('db_connection') ?: config('database.default'));
    }

    protected static function chosenMailer(Get $get): string
    {
        return (string) ($get('mail_mailer') ?: config('mail.default'));
    }

    /**
     * The value the configuration falls back on while a missing variable is not set (e.g. `127.0.0.1` for `DB_HOST`):
     * for the database, the one of the connection chosen. The passwords have none worth showing.
     */
    protected function environmentFallback(string $variable, Get $get): ?string
    {
        if (str_ends_with($variable, '_PASSWORD') || ! in_array($variable, $this->missingVariables, true)) {
            return null;
        }

        $connection = self::chosenConnection($get);
        $value = config(str_replace('{connection}', $connection, self::ENVIRONMENT_VARIABLES[$variable]));

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
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
        // Left empty, the connection and the mailer are the ones the configuration falls back on
        $connection = $this->isEditableVariable('DB_CONNECTION') ? (string) ($this->data['db_connection'] ?? '') : '';
        $connection = $connection !== '' ? $connection : (string) config('database.default');
        $mailer = $this->isEditableVariable('MAIL_MAILER') ? (string) ($this->data['mail_mailer'] ?? '') : '';
        $mailer = $mailer !== '' ? $mailer : (string) config('mail.default');

        $typed = collect(array_keys(self::ENVIRONMENT_VARIABLES))
            ->mapWithKeys(fn (string $variable): array => [$variable => trim((string) ($this->data[strtolower($variable)] ?? ''))])
            ->put('APP_URL', rtrim(trim((string) ($this->data['app_url'] ?? '')), '/'))
            // Asked for by the Background tasks step, which saves them
            ->except(self::TASKS_VARIABLES)
            // The fields hidden for the chosen database and mailer keep their default value: it is not written
            ->except($connection === 'sqlite' ? ['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] : [])
            ->except($mailer !== 'smtp' ? ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD'] : [])
            // Left empty, a missing value keeps the fallback of the configuration, and a value already set is kept
            ->filter(fn (string $value): bool => $value !== '');

        $values = $typed->only($this->missingVariables)->all();

        // The variables already set that were changed: written over their current value
        $changedValues = $typed
            ->only($this->changeableVariables)
            ->reject(fn (string $value, string $variable): bool => $value === $this->currentEnvironmentValue($variable))
            ->all();

        $this->checkDatabase($connection, [...$values, ...$changedValues]);
        $this->applyToConfiguration([...$values, ...$changedValues]);

        try {
            app(EnvironmentFile::class)->writeMissing($values);

            foreach ($changedValues as $variable => $value) {
                app(EnvironmentFile::class)->set($variable, $value);
            }

            $this->createVapidKeys();
            $this->createCronToken();
        } catch (Throwable $exception) {
            $this->stopStep(__('setup.environment.write_failed'), $exception->getMessage());
        }

        $this->createPassportKeys();

        // A cached configuration would hide the new values from the next requests
        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        Installation::forget();
        $this->refreshEnvironmentVariables();
    }

    /**
     * Save the queue chosen by the Background tasks step.
     */
    protected function saveTasks(): void
    {
        $queue = trim((string) ($this->data['queue_connection'] ?? ''));

        if ($queue === '') {
            return;
        }

        try {
            if (in_array('QUEUE_CONNECTION', $this->missingVariables, true)) {
                app(EnvironmentFile::class)->writeMissing(['QUEUE_CONNECTION' => $queue]);
            } elseif (in_array('QUEUE_CONNECTION', $this->changeableVariables, true) && $queue !== $this->currentEnvironmentValue('QUEUE_CONNECTION')) {
                app(EnvironmentFile::class)->set('QUEUE_CONNECTION', $queue);
            }
        } catch (Throwable $exception) {
            $this->stopStep(__('setup.environment.write_failed'), $exception->getMessage());
        }

        config(['queue.default' => $queue]);

        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        $this->refreshEnvironmentVariables();
    }

    /**
     * Create the secret of the endpoints that run the scheduler and the queue (`ARTISAN_CRON_TOKEN`) when missing, for
     * the hosting without a shell, where an external service calls them.
     */
    protected function createCronToken(): void
    {
        if (! app(EnvironmentFile::class)->isMissing('ARTISAN_CRON_TOKEN')) {
            return;
        }

        $token = Str::random(64);

        app(EnvironmentFile::class)->writeMissing(['ARTISAN_CRON_TOKEN' => $token]);
        config(['app.artisan_cron_token' => $token]);
    }

    /**
     * The connections of the queue (`config/queue.php`), with a description of the most common ones.
     *
     * @return array<string, string>
     */
    public static function queueOptions(): array
    {
        return collect(array_keys((array) config('queue.connections')))
            ->mapWithKeys(fn (string $connection): array => [
                $connection => Lang::has("setup.tasks.queues.{$connection}") ? __("setup.tasks.queues.{$connection}") : $connection,
            ])
            ->all();
    }

    protected static function chosenQueue(Get $get): string
    {
        return (string) ($get('queue_connection') ?: config('queue.default'));
    }

    /**
     * The address of the application: the one of `.env`, or else the one of the page.
     */
    protected function applicationUrl(): string
    {
        $url = in_array('APP_URL', $this->missingVariables, true) ? $this->requestRoot : (string) config('app.url');

        return rtrim($url, '/');
    }

    /**
     * The keys the Configuration step creates when missing: whether they exist, or are created on going on.
     *
     * @return list<array{key: string, label: string, status: 'set'|'created'|'unavailable', details: ?string}>
     */
    protected function keysStatus(): array
    {
        $writable = app(EnvironmentFile::class)->isWritable();

        return [
            [
                'key' => 'push',
                'label' => __('setup.environment.push_keys'),
                'status' => ! $this->hasMissingVapidKeys() ? 'set' : ($writable ? 'created' : 'unavailable'),
                'details' => $this->hasMissingVapidKeys() && ! $writable ? __('setup.environment.push_not_writable') : null,
            ],
            [
                'key' => 'passport',
                'label' => __('setup.environment.passport_keys'),
                'status' => self::hasPassportKeys() ? 'set' : 'created',
                'details' => null,
            ],
        ];
    }

    /**
     * Whether the keys signing the tokens of the AI assistants exist.
     */
    public static function hasPassportKeys(): bool
    {
        return file_exists(Passport::keyPath('oauth-private.key')) && file_exists(Passport::keyPath('oauth-public.key'));
    }

    /**
     * Create the keys signing the tokens of the AI assistants, only when missing (as `och:install` does): replacing
     * them would disconnect every assistant.
     */
    protected function createPassportKeys(): void
    {
        try {
            $exitCode = Artisan::call('och:ensure-passport-keys');
        } catch (Throwable $exception) {
            report($exception);
            $exitCode = 1;
        }

        if ($exitCode !== 0) {
            $this->stopStep(__('setup.environment.keys_failed', ['command' => 'php artisan och:ensure-passport-keys']), trim(Artisan::output()));
        }
    }

    /**
     * Whether both keys of the push notifications are missing: one set alone is left as it is, as replacing a key would
     * unsubscribe every device.
     */
    protected function hasMissingVapidKeys(): bool
    {
        return app(EnvironmentFile::class)->missing(self::VAPID_VARIABLES) === self::VAPID_VARIABLES;
    }

    /**
     * Create the keys of the push notifications (as `php artisan webpush:vapid` does) when both are missing, so that
     * the devices can subscribe to them right away.
     */
    protected function createVapidKeys(): void
    {
        if (! $this->hasMissingVapidKeys()) {
            return;
        }

        $keys = VAPID::createVapidKeys();

        app(EnvironmentFile::class)->writeMissing([
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
        ]);

        config([
            'webpush.vapid.public_key' => $keys['publicKey'],
            'webpush.vapid.private_key' => $keys['privateKey'],
        ]);
    }

    /**
     * Find the variables of `.env` that the Configuration step asks for, and those it can change.
     */
    protected function refreshEnvironmentVariables(): void
    {
        $environmentFile = app(EnvironmentFile::class);

        $this->missingVariables = $environmentFile->missing(array_keys(self::ENVIRONMENT_VARIABLES));
        $this->changeableVariables = array_values(array_filter(
            self::CHANGEABLE_VARIABLES,
            fn (string $variable): bool => ! in_array($variable, $this->missingVariables, true) && ! $environmentFile->isSetOutsideFile($variable),
        ));
    }

    /**
     * Whether the Configuration step lets the user type the value of a variable: when it is missing, or can be changed.
     */
    protected function isEditableVariable(string $variable): bool
    {
        return in_array($variable, $this->missingVariables, true) || in_array($variable, $this->changeableVariables, true);
    }

    /**
     * Whether the mailer in use keeps the emails instead of sending them (e.g. writing them in the log), so that the test
     * email reaches no inbox.
     */
    public static function mailerDoesNotSend(): bool
    {
        return in_array(config('mail.default'), self::NOT_SENDING_MAILERS, true);
    }

    /**
     * The mailers of the configuration (`config/mail.php`), with a description of the ones not named after a service.
     *
     * @return array<string, string>
     */
    public static function mailerOptions(): array
    {
        return collect(array_keys((array) config('mail.mailers')))
            ->mapWithKeys(fn (string $mailer): array => [
                $mailer => Lang::has("setup.environment.mailers.{$mailer}")
                    ? __("setup.environment.mailers.{$mailer}")
                    : (self::MAILER_NAMES[$mailer] ?? $mailer),
            ])
            ->all();
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
     * Create the tables and the permissions of the roles, as `och:install` does.
     */
    protected function setUpDatabase(): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $steps = [
            ['migrate', ['--force' => true]],
            ['db:seed', ['--class' => ShieldSeeder::class, '--force' => true]],
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
            ->body(self::mailerDoesNotSend()
                ? __('setup.root.mailer_not_sending', ['mailer' => (string) config('mail.default')])
                : __('setup.checks.test_email.sent_body'))
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

        $this->saveInitialRootUser($data);

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
     * Write the root user in `INITIAL_ROOT_*`, replacing the values of `.env`, so that `och:recover-root` (and the login
     * pre-filled on the demos) use the account just created. The variables set by the environment of the server are
     * left as they are, as writing `.env` would not change them.
     *
     * @param  array<string, mixed>  $data
     */
    protected function saveInitialRootUser(array $data): void
    {
        $environmentFile = app(EnvironmentFile::class);

        $values = [
            'INITIAL_ROOT_USERNAME' => trim($data['root_first_name'].' '.$data['root_last_name']),
            'INITIAL_ROOT_USERMAIL' => (string) $data['root_email'],
            'INITIAL_ROOT_PASSWORD' => (string) $data['root_password'],
        ];

        try {
            foreach ($values as $variable => $value) {
                if (! $environmentFile->isSetOutsideFile($variable)) {
                    $environmentFile->set($variable, $value);
                }
            }
        } catch (Throwable $exception) {
            // The application is installed all the same: only the recovery of the root user needs them
            report($exception);
        }

        config(['app.initial_root_user' => [
            'name' => $values['INITIAL_ROOT_USERNAME'],
            'email' => $values['INITIAL_ROOT_USERMAIL'],
            'password' => $values['INITIAL_ROOT_PASSWORD'],
        ]]);
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
