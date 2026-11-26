<?php

use App\Filament\Pages\EditProfile;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole;
use BezhanSalleh\FilamentShield\Support\Utils;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
    Every page is open to everyone (profile), only to the super administrators (settings, logs) or enabled by a
    feature (API tokens): their permissions would do nothing, so the roles don't offer them.
*/

beforeEach(function () {
    Filament::setCurrentPanel('app');
});

test('no page has a permission of its own', function () {
    expect(FilamentShield::getPages())->toBe([])
        ->and(Utils::isPageTabEnabled())->toBeFalse()
        ->and(collect(FilamentShield::getEntitiesPermissions()))->not->toContain('View:EditProfile', 'View:Logs', 'View:ApiTokens');
});

test('the role form has no Pages tab', function () {
    User::factory()->create(['id' => 1]);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($superAdmin);

    Livewire::test(CreateRole::class)
        ->assertSeeText(__('filament-shield::filament-shield.resources'))
        ->assertDontSeeText(__('filament-shield::filament-shield.pages'));
});

test('the shield seeder creates no page permissions', function () {
    $this->seed(ShieldSeeder::class);

    expect(Permission::whereIn('name', ['View:EditProfile', 'View:Logs', 'View:ApiTokens'])->exists())->toBeFalse();
});

test('a user with no permissions can open their profile', function () {
    User::factory()->create(['id' => 1]);
    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    expect(EditProfile::canAccess())->toBeTrue();
});
