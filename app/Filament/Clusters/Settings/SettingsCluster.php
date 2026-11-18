<?php

namespace App\Filament\Clusters\Settings;

use App\Enums\NavigationGroup;
use App\Models\User;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * The Settings pages, one per area, where the super administrators change the options of the application instead of
 * editing `.env` (see `App\Settings\ConfigSettings`).
 */
class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'settings';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasRole('super_admin');
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.navigation_label');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('settings.navigation_label');
    }
}
