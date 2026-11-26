<?php

namespace App\Filament\Pages;

use App\Enums\InstallationCheckStatus;
use App\Filament\Clusters\Settings\Pages\ManageGeneralSettings;
use App\Filament\Clusters\Settings\Pages\ManageSecuritySettings;
use App\Models\User;
use App\Notifications\TestEmailNotification;
use App\Notifications\UserInvitationNotification;
use App\Settings\GeneralSettings;
use App\Settings\SecuritySettings;
use App\Settings\TerritorySettings;
use App\Support\AiAssistantConnections;
use App\Support\InstallationChecks;
use BackedEnum;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * The first configuration of a new installation, for the root user: the checks of the installation, the name and the
 * appearance of the application, the administrators and the features to enable.
 *
 * It is offered right after logging in (see `RedirectToSetupWizard`) until it is completed or skipped, and everything
 * it sets can be changed later from the Settings pages and the users.
 *
 * @property-read Schema $form
 * @property-read list<array{key: string, check: string, status: InstallationCheckStatus, details: string}> $installationChecks
 */
class SetupWizard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $slug = 'setup';

    /**
     * Session key set when the root user logs in while the setup is not completed (see `OfferSetupWizardAfterLogin`),
     * and taken by the first page, which opens the wizard instead.
     */
    public const OFFER_SESSION_KEY = 'setup_wizard.offer';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->isRootUser()
            && ! app(GeneralSettings::class)->setup_completed;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return __('setup.title');
    }

    public function getSubheading(): string
    {
        return __('setup.subheading');
    }

    public function mount(): void
    {
        $general = app(GeneralSettings::class);
        $security = app(SecuritySettings::class);
        $territories = app(TerritorySettings::class);

        $this->form->fill([
            'app_name' => $general->app_name ?? config('app.name'),
            'kingdom_hall_address' => $general->kingdom_hall_address,
            'logo' => $general->logo,
            'primary_color' => $general->primary_color,
            'administrators' => [],
            'ai_assistants_enabled' => $security->ai_assistants_enabled,
            'sharing_enabled' => $territories->sharing_enabled,
            'guest_returns' => (bool) $territories->guest_returns,
            'require_two_factor' => $security->require_two_factor,
            'require_email_verification' => $security->require_email_verification,
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('skip')
                ->label(__('setup.skip.label'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('setup.skip.heading'))
                ->modalDescription(__('setup.skip.description'))
                ->modalSubmitActionLabel(__('setup.skip.confirm'))
                ->action(function (): void {
                    $this->markCompleted();
                    $this->redirect(Filament::getUrl());
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('complete'),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    $this->installationStep(),
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

    protected function installationStep(): Step
    {
        return Step::make('installation')
            ->label(__('setup.steps.installation.label'))
            ->description(__('setup.steps.installation.description'))
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->schema([
                View::make('filament.setup.installation-checks')
                    ->viewData(fn (): array => ['checks' => $this->installationChecks]),
                Actions::make([
                    Action::make('checkAgain')
                        ->label(__('setup.checks.check_again'))
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('gray')
                        ->action(function (): void {
                            unset($this->installationChecks);
                        }),
                    Action::make('sendTestEmail')
                        ->label(__('setup.checks.test_email.label'))
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->color('gray')
                        ->action(fn () => $this->sendTestEmail()),
                    Action::make('createStorageLink')
                        ->label(__('setup.checks.storage_link'))
                        ->icon(Heroicon::OutlinedLink)
                        ->visible(fn (): bool => $this->checkFailed('storage_link'))
                        ->action(fn () => $this->runCommand('storage:link', ['--force' => true])),
                    Action::make('createAiAssistantKeys')
                        ->label(__('setup.checks.passport_keys'))
                        ->icon(Heroicon::OutlinedKey)
                        ->visible(fn (): bool => $this->checkFailed('passport_keys'))
                        ->action(fn () => $this->runCommand('och:ensure-passport-keys')),
                ])->key('installationActions'),
            ]);
    }

    protected function congregationStep(): Step
    {
        return Step::make('congregation')
            ->label(__('setup.steps.congregation.label'))
            ->description(__('setup.steps.congregation.description'))
            ->icon(Heroicon::OutlinedHome)
            ->columns(2)
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
                Text::make(fn (): string => __('setup.administrators.intro', ['email' => Auth::user()?->email])),
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
                        ManageSecuritySettings::requireTwoFactorField(),
                        ManageSecuritySettings::requireEmailVerificationField(),
                    ]),
            ]);
    }

    /**
     * The checks of the installation, run again by "Check again" and after fixing one of them.
     *
     * @return list<array{key: string, check: string, status: InstallationCheckStatus, details: string}>
     */
    #[Computed]
    public function installationChecks(): array
    {
        return app(InstallationChecks::class)->run();
    }

    protected function checkFailed(string $key): bool
    {
        return collect($this->installationChecks)
            ->contains(fn (array $result): bool => $result['key'] === $key && $result['status'] !== InstallationCheckStatus::Ok);
    }

    /**
     * Run a command fixing a check, as `och:install` and `och:post-deploy` do.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function runCommand(string $command, array $arguments = []): void
    {
        try {
            $exitCode = Artisan::call($command, $arguments);
        } catch (Throwable $exception) {
            report($exception);
            $exitCode = 1;
        }

        unset($this->installationChecks);

        $exitCode === 0
            ? Notification::make()->success()->title(__('setup.checks.fixed'))->send()
            : Notification::make()->danger()->title(__('setup.checks.fix_failed', ['command' => "php artisan {$command}"]))->send();
    }

    /**
     * Send an email to the root user, to check that the emails reach their recipients.
     */
    protected function sendTestEmail(): void
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            // Right away, also when the notifications are queued, so that the errors are shown here
            $user->notifyNow(new TestEmailNotification);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('setup.checks.test_email.failed'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('setup.checks.test_email.sent', ['email' => $user->email]))
            ->body(__('setup.checks.test_email.sent_body'))
            ->send();
    }

    /**
     * Save the settings, create the administrators and send them their invitation, then mark the setup as completed.
     */
    public function complete(): void
    {
        $data = $this->form->getState();

        $general = app(GeneralSettings::class);
        $replacedLogo = $general->logo;

        $administrators = DB::transaction(function () use ($data, $general): array {
            $general->fill([
                'app_name' => trim((string) $data['app_name']),
                'kingdom_hall_address' => $data['kingdom_hall_address'] ?? null,
                'logo' => $data['logo'] ?? null,
                'primary_color' => $data['primary_color'] ?? null,
                'setup_completed' => true,
            ])->save();

            app(SecuritySettings::class)->fill([
                'ai_assistants_enabled' => (bool) $data['ai_assistants_enabled'],
                'require_two_factor' => (bool) $data['require_two_factor'],
                'require_email_verification' => (bool) $data['require_email_verification'],
            ])->save();

            app(TerritorySettings::class)->fill([
                'sharing_enabled' => (bool) $data['sharing_enabled'],
                'guest_returns' => (bool) $data['guest_returns'],
            ])->save();

            $role = Utils::createRole();

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

        ManageGeneralSettings::deleteReplacedLogo($replacedLogo, $general->logo);

        if (! app(SecuritySettings::class)->ai_assistants_enabled) {
            AiAssistantConnections::revokeAll();
        }

        foreach ($administrators as $administrator) {
            UserInvitationNotification::sendTo($administrator);
        }

        Notification::make()
            ->success()
            ->title(__('setup.completed.title'))
            ->body(trans_choice('setup.completed.body', count($administrators), ['count' => count($administrators)]))
            ->send();

        $this->redirect(Filament::getUrl());
    }

    protected function markCompleted(): void
    {
        $general = app(GeneralSettings::class);
        $general->setup_completed = true;
        $general->save();
    }
}
