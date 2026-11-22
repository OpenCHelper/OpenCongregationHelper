<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\AppSettingsPage;
use App\Models\User;
use App\Settings\SecuritySettings;
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
                        Toggle::make('require_two_factor')
                            ->label(__('settings.fields.require_two_factor.label'))
                            ->helperText(__('settings.fields.require_two_factor.helper'))
                            ->rule($this->rootUserMustMeet(
                                fn (User $root): bool => $root->hasTwoFactorAuthentication(),
                                __('settings.fields.require_two_factor.root_required'),
                            )),
                        Toggle::make('require_email_verification')
                            ->label(__('settings.fields.require_email_verification.label'))
                            ->helperText(__('settings.fields.require_email_verification.helper'))
                            ->rule($this->rootUserMustMeet(
                                fn (User $root): bool => $root->hasVerifiedEmail(),
                                __('settings.fields.require_email_verification.root_required'),
                            )),
                    ]),
            ]);
    }

    /**
     * Rule preventing to turn a requirement on while the root user does not meet it, so that the root user can never be
     * locked out of the app (e.g. when the emails cannot be delivered).
     *
     * @param  Closure(User): bool  $isMetByRootUser
     */
    protected function rootUserMustMeet(Closure $isMetByRootUser, string $message): Closure
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
