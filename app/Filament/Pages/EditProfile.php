<?php

namespace App\Filament\Pages;

use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditProfile extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected string $view = 'filament.pages.edit-profile';

    protected static ?string $navigationLabel = null;

    /**
     * Translated title, otherwise Filament derives "Edit Profile" from the name of the class.
     */
    public function getTitle(): string
    {
        return __('profile.header.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-resources.user_profile.navigation_label');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    /**
     * The management of the two-factor authentication (Filament), as on the profile page of Filament: the users can
     * enable it even when the Security settings do not require it.
     */
    public function multiFactorAuthentication(Schema $schema): Schema
    {
        return $schema
            ->components(collect(Filament::getMultiFactorAuthenticationProviders())
                ->map(fn (MultiFactorAuthenticationProvider $provider): Group => Group::make($provider->getManagementSchemaComponents())
                    ->statePath($provider->getId()))
                ->all());
    }
}
