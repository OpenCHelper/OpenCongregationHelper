<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Lab404\Impersonate\Services\ImpersonateManager;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    $this->rootUser = User::factory()->create(['id' => 1, 'can_login' => true]);
    $this->superAdmin = User::factory()->create(['can_login' => true]);
    $this->superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));

    $this->manager = User::factory()->create(['can_login' => true]);
    $this->manager->givePermissionTo(Permission::create(['name' => 'ViewAny:User']));

    $this->regularUser = User::factory()->create(['can_login' => true]);
    $this->virtualUser = User::factory()->create(['can_login' => false]);
});

test('only super administrators can impersonate', function () {
    expect($this->superAdmin->canImpersonate())->toBeTrue()
        ->and($this->manager->canImpersonate())->toBeFalse()
        ->and($this->regularUser->canImpersonate())->toBeFalse();
});

test('every user except root can be impersonated, virtual users included', function () {
    expect($this->regularUser->canBeImpersonated())->toBeTrue()
        ->and($this->virtualUser->canBeImpersonated())->toBeTrue()
        ->and($this->rootUser->canBeImpersonated())->toBeFalse();
});

test('the impersonate action is hidden for users that cannot impersonate', function () {
    $this->actingAs($this->manager);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('impersonate')->table($this->regularUser))
        ->assertActionHidden(TestAction::make('impersonate')->table($this->rootUser));
});

test('super administrators can impersonate virtual users but not root', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('impersonate')->table($this->virtualUser))
        ->assertActionVisible(TestAction::make('impersonate')->table($this->regularUser))
        ->assertActionHidden(TestAction::make('impersonate')->table($this->rootUser));
});

test('virtual users can access the panel only while being impersonated', function () {
    $panel = Filament::getPanel('app');

    expect($this->virtualUser->canAccessPanel($panel))->toBeFalse();

    session()->put(app(ImpersonateManager::class)->getSessionKey(), $this->superAdmin->id);

    expect($this->virtualUser->canAccessPanel($panel))->toBeTrue();
});
