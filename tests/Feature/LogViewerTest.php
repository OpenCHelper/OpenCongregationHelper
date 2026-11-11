<?php

use App\Enums\NavigationGroup;
use App\Filament\Pages\Logs;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('app');
});

test('super administrators can open the log viewer', function () {
    $superAdmin = User::factory()->create(['can_login' => true]);
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));

    $this->actingAs($superAdmin);

    Livewire::test(Logs::class)
        ->assertOk();
});

test('other users cannot open the log viewer', function () {
    $this->actingAs(User::factory()->create(['can_login' => true]));

    Livewire::test(Logs::class)
        ->assertForbidden();
});

test('the log viewer is listed in the developer navigation group', function () {
    expect(Logs::getNavigationGroup())->toBe(NavigationGroup::Developer->name)
        ->and(Logs::getSlug())->toBe('logs');
});

test('the log viewer navigation label follows the current locale', function () {
    expect(__('filament-log-viewer::log.navigation.label', locale: 'en'))
        ->not->toBe(__('filament-log-viewer::log.navigation.label', locale: 'it'));

    foreach (['en', 'it'] as $locale) {
        app()->setLocale($locale);

        expect(Logs::getNavigationLabel())->toBe(__('filament-log-viewer::log.navigation.label'));
    }
});
