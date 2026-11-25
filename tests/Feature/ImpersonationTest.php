<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Facades\Impersonation;
use STS\FilamentImpersonate\ImpersonateManager;

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

test('impersonating asks for a confirmation, from the list and from the page of the user', function () {
    $this->actingAs($this->superAdmin);
    $requiresConfirmation = fn (Action $action): bool => $action->isConfirmationRequired();

    Livewire::test(ListUsers::class)
        ->assertActionExists(TestAction::make('impersonate')->table($this->regularUser), $requiresConfirmation);

    Livewire::test(ViewUser::class, ['record' => $this->regularUser->getRouteKey()])
        ->assertActionExists('impersonate', $requiresConfirmation)
        ->mountAction('impersonate')
        ->assertActionMounted('impersonate');

    expect(app(ImpersonateManager::class)->isImpersonating())->toBeFalse();
});

test('virtual users can access the panel only while being impersonated', function () {
    $panel = Filament::getPanel('app');

    expect($this->virtualUser->canAccessPanel($panel))->toBeFalse();

    session()->put(ImpersonateManager::SESSION_KEY, $this->superAdmin->id);

    expect($this->virtualUser->canAccessPanel($panel))->toBeTrue();
});

test('super administrators can impersonate a virtual user and then leave the impersonation', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('impersonate')->table($this->virtualUser))
        ->assertRedirect('/');

    expect(auth()->id())->toBe($this->virtualUser->id)
        ->and(Impersonation::isImpersonating())->toBeTrue()
        ->and(Impersonation::getImpersonatorId())->toBe($this->superAdmin->id);

    $this->get('/app')
        ->assertSuccessful()
        ->assertSee(__('filament-impersonate::banner.impersonating'), false);

    $this->get(route('filament-impersonate.leave'))->assertRedirect();

    expect(auth()->id())->toBe($this->superAdmin->id)
        ->and(Impersonation::isImpersonating())->toBeFalse();
});
