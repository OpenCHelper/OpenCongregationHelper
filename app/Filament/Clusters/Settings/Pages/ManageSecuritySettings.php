<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\AppSettingsPage;
use App\Models\User;
use App\Settings\SecuritySettings;
use App\Support\AiAssistantConnections;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSecuritySettings extends AppSettingsPage
{
    protected static string $settings = SecuritySettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'security';

    public static function getNavigationLabel(): string
    {
        return __('settings.pages.security');
    }

    public function getTitle(): string
    {
        return __('settings.pages.security');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('settings.sections.account_requirements'))
                    ->description(__('settings.sections.account_requirements_description'))
                    ->columnSpanFull()
                    ->schema([
                        self::requireTwoFactorField(),
                        self::requireEmailVerificationField(),
                    ]),
                Section::make(__('settings.sections.passwords'))
                    ->description(__('settings.sections.passwords_description'))
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('password_leak_check')
                            ->label(__('settings.fields.password_leak_check.label'))
                            ->helperText(__('settings.fields.password_leak_check.helper').' '.$this->defaultHint(
                                $this->defaultValue('password_leak_check') ? __('settings.fields.password_leak_check.enabled') : __('settings.fields.password_leak_check.disabled'),
                            )),
                    ]),
                Section::make(__('settings.sections.ai_assistants'))
                    ->description(__('settings.sections.ai_assistants_description'))
                    ->columnSpanFull()
                    ->schema([
                        self::aiAssistantsEnabledField(),
                    ]),
            ]);
    }

    /**
     * The fields below are shared with the first-run setup wizard (`SetupWizard`).
     */
    public static function requireTwoFactorField(): Toggle
    {
        return Toggle::make('require_two_factor')
            ->label(__('settings.fields.require_two_factor.label'))
            ->helperText(__('settings.fields.require_two_factor.helper'))
            ->rule(self::rootUserMustMeet(
                fn (User $root): bool => $root->hasTwoFactorAuthentication(),
                __('settings.fields.require_two_factor.root_required'),
            ));
    }

    public static function requireEmailVerificationField(): Toggle
    {
        return Toggle::make('require_email_verification')
            ->label(__('settings.fields.require_email_verification.label'))
            ->helperText(__('settings.fields.require_email_verification.helper'))
            ->rule(self::rootUserMustMeet(
                fn (User $root): bool => $root->hasVerifiedEmail(),
                __('settings.fields.require_email_verification.root_required'),
            ));
    }

    public static function aiAssistantsEnabledField(): Toggle
    {
        return Toggle::make('ai_assistants_enabled')
            ->label(__('settings.fields.ai_assistants_enabled.label'))
            ->helperText(__('settings.fields.ai_assistants_enabled.helper'));
    }

    /**
     * Turning the AI assistants off disconnects them all: they must be authorized again once turned back on.
     */
    protected function afterSave(): void
    {
        if (! app(SecuritySettings::class)->ai_assistants_enabled) {
            AiAssistantConnections::revokeAll();
        }
    }

    /**
     * Rule preventing to turn a requirement on while the root user does not meet it, so that the root user can never be
     * locked out of the app (e.g. when the emails cannot be delivered).
     *
     * @param  Closure(User): bool  $isMetByRootUser
     */
    protected static function rootUserMustMeet(Closure $isMetByRootUser, string $message): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($isMetByRootUser, $message): void {
            if (! $value) {
                return;
            }

            $root = User::rootUser();

            if ($root === null || ! $isMetByRootUser($root)) {
                $fail($message);
            }
        };
    }
}
