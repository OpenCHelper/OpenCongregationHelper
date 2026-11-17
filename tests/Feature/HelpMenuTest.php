<?php

use App\Filament\Resources\Territories\TerritoryResource;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    // Render the real assets when they are built (as in CI), and fake them otherwise (e.g. on a fresh clone)
    if (! frontendAssetsAreAvailable()) {
        $this->withoutVite();
    }
});

test('the help menu is wrapped in the top bar, after the environment indicator and before the search', function () {
    $superAdmin = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]);
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));

    $this->actingAs($superAdmin)
        ->get(TerritoryResource::getUrl())
        ->assertOk()
        ->assertSeeInOrder([
            'environment-indicator',
            '<div class="app-help-menu">',
            __('filament-knowledge-base::translations.help'),
            'fi-global-search',
        ], escape: false);
});
