<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    $this->rootUser = User::factory()->create(['id' => 1]);

    $this->manager = User::factory()->create();
    $this->manager->givePermissionTo(Permission::create(['name' => 'ViewAny:User']));
    $this->actingAs($this->manager);
});

test('users can be sorted by role', function () {
    $editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $viewerRole = Role::create(['name' => 'viewer', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $viewer = User::factory()->create();
    $viewer->assignRole($viewerRole);

    // Users with several roles are sorted by the first one in alphabetical order
    $editorAndViewer = User::factory()->create();
    $editorAndViewer->assignRole($viewerRole, $editorRole);

    Livewire::test(ListUsers::class)
        ->toggleAllTableColumns()
        ->sortTable('roles.name')
        ->assertCanSeeTableRecords([$admin, $editorAndViewer, $viewer], inOrder: true)
        ->sortTable('roles.name', 'desc')
        ->assertCanSeeTableRecords([$viewer, $editorAndViewer, $admin], inOrder: true);
});

test('users can be filtered by access, with the number of users of each option', function () {
    $userWithAccess = User::factory()->create();
    $virtualUsers = User::factory()->virtual()->count(2)->create();

    $component = Livewire::test(ListUsers::class);

    $filter = $component->instance()->getTable()->getFilter('can_login');
    expect($filter->getPlaceholder())->toBe(__('filament-resources.user.filter_access_all', ['count' => 5]))
        ->and($filter->getTrueLabel())->toBe(__('filament-resources.user.filter_access_with_access', ['count' => 3]))
        ->and($filter->getFalseLabel())->toBe(__('filament-resources.user.filter_access_virtual', ['count' => 2]));

    $component
        ->filterTable('can_login', true)
        ->assertCanSeeTableRecords([$this->manager, $userWithAccess])
        ->assertCanNotSeeTableRecords($virtualUsers)
        ->assertSee(__('filament-resources.user.filter_access_indicator_with_access'))
        ->filterTable('can_login', false)
        ->assertCanSeeTableRecords($virtualUsers)
        ->assertCanNotSeeTableRecords([$this->manager, $userWithAccess])
        ->assertSee(__('filament-resources.user.filter_access_indicator_virtual'));
});

test('secondary columns are hidden by default', function () {
    Livewire::test(ListUsers::class)
        ->assertCanRenderTableColumn('departments')
        ->assertCanRenderTableColumn('can_login')
        ->tap(fn ($component) => expect($component->instance()->getTable()->getColumn('can_login')->isToggleable())->toBeTrue())
        ->assertCanNotRenderTableColumn('roles.name')
        ->assertCanNotRenderTableColumn('email_verified_at')
        ->assertCanNotRenderTableColumn('two_factor_authentication')
        ->toggleAllTableColumns()
        ->assertCanRenderTableColumn('roles.name')
        ->assertCanRenderTableColumn('email_verified_at')
        ->assertCanRenderTableColumn('two_factor_authentication');
});

test('the card shown on phones has the name, the email and the enabled columns of the user', function () {
    $role = Role::create(['name' => 'card-role', 'guard_name' => 'web']);
    $user = User::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'mario.rossi@example.com']);
    $user->assignRole($role);

    $component = Livewire::test(ListUsers::class);
    $renderCard = fn (): string => $component->instance()->getTable()->getColumn('card')->record($user)->toHtml();

    expect($renderCard())
        ->toContain('Mario Rossi')
        ->toContain('mario.rossi@example.com')
        ->toContain('fi-user-avatar')
        ->not->toContain('card-role')
        ->not->toContain(__('filament-resources.user.column_two_factor_authentication_short'));

    $component->toggleAllTableColumns();

    expect($renderCard())
        ->toContain('card-role')
        ->toContain(__('filament-resources.user.column_two_factor_authentication_short'));
});

test('users can be sorted by the card shown on phones, by last and first name', function () {
    User::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);
    User::factory()->create(['first_name' => 'Anna', 'last_name' => 'Rossi']);
    User::factory()->create(['first_name' => 'Zeno', 'last_name' => 'Bianchi']);

    Livewire::test(ListUsers::class)
        ->sortTable('card')
        ->assertCanSeeTableRecords(
            User::query()->orderBy('last_name')->orderBy('first_name')->get(),
            inOrder: true,
        );
});

test('the two-factor authentication column tells whether the user has set it up', function () {
    $userWithTwoFactorAuthentication = User::factory()->create(['app_authentication_secret' => 'secret']);
    $userWithoutTwoFactorAuthentication = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertTableColumnStateSet('two_factor_authentication', true, $userWithTwoFactorAuthentication)
        ->assertTableColumnStateSet('two_factor_authentication', false, $userWithoutTwoFactorAuthentication);
});

test('users can be filtered by email verification', function () {
    $verifiedUser = User::factory()->create(['email_verified_at' => now()]);
    $unverifiedUser = User::factory()->create(['email_verified_at' => null]);

    $component = Livewire::test(ListUsers::class);

    // The root user and the manager of `beforeEach()` are not verified (the default of the factory)
    $filter = $component->instance()->getTable()->getFilter('email_verified_at');
    expect($filter->getPlaceholder())->toBe(__('filament-resources.user.filter_all', ['count' => 4]))
        ->and($filter->getTrueLabel())->toBe(__('filament-resources.user.filter_email_verified_yes', ['count' => 1]))
        ->and($filter->getFalseLabel())->toBe(__('filament-resources.user.filter_email_verified_no', ['count' => 3]));

    $component
        ->filterTable('email_verified_at', true)
        ->assertCanSeeTableRecords([$verifiedUser])
        ->assertCanNotSeeTableRecords([$unverifiedUser])
        ->filterTable('email_verified_at', false)
        ->assertCanSeeTableRecords([$unverifiedUser])
        ->assertCanNotSeeTableRecords([$verifiedUser])
        ->assertSee(__('filament-resources.user.filter_email_verified_indicator_no'));
});

test('users can be filtered by two-factor authentication', function () {
    $userWithTwoFactorAuthentication = User::factory()->create(['app_authentication_secret' => 'secret']);
    $userWithoutTwoFactorAuthentication = User::factory()->create();

    $component = Livewire::test(ListUsers::class);

    $filter = $component->instance()->getTable()->getFilter('two_factor_authentication');
    expect($filter->getPlaceholder())->toBe(__('filament-resources.user.filter_all', ['count' => 4]))
        ->and($filter->getTrueLabel())->toBe(__('filament-resources.user.filter_two_factor_authentication_yes', ['count' => 1]))
        ->and($filter->getFalseLabel())->toBe(__('filament-resources.user.filter_two_factor_authentication_no', ['count' => 3]));

    $component
        ->filterTable('two_factor_authentication', true)
        ->assertCanSeeTableRecords([$userWithTwoFactorAuthentication])
        ->assertCanNotSeeTableRecords([$userWithoutTwoFactorAuthentication])
        ->filterTable('two_factor_authentication', false)
        ->assertCanSeeTableRecords([$userWithoutTwoFactorAuthentication])
        ->assertCanNotSeeTableRecords([$userWithTwoFactorAuthentication])
        ->assertSee(__('filament-resources.user.filter_two_factor_authentication_indicator_no'));
});
