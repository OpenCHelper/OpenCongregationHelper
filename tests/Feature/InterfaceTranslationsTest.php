<?php

use App\Filament\Resources\Territories\Pages\CreateTerritory;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\ContactsRelationManager;
use App\Models\User;
use App\Providers\Filament\AppPanelProvider;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Laravel\Jetstream\Features;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');
    App::setLocale('it');
});

test('the territory image fields are translated', function () {
    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    foreach (['ViewAny:Territory', 'Create:Territory'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    Livewire::test(CreateTerritory::class)
        ->assertSee(['Immagine da URL', '— oppure —', 'Immagine da file'])
        ->assertDontSee(['Image from URL', '— or —', 'Image from file']);
});

test('the contacts of a user have a singular and a plural label', function () {
    expect(ContactsRelationManager::getModelLabel())->toBe('Contatto')
        ->and(ContactsRelationManager::getPluralModelLabel())->toBe('Contatti');

    $superAdmin = User::factory()->create(['can_login' => true, 'email_verified_at' => now()]);
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($superAdmin);

    Livewire::test(ContactsRelationManager::class, ['ownerRecord' => User::factory()->create(), 'pageClass' => EditUser::class])
        ->mountAction(TestAction::make('create')->table())
        ->assertMountedActionModalSee(['Crea Contatto', 'Note...'])
        ->assertMountedActionModalDontSee(['Crea Contatti', 'Notes...']);
});

test('the API tokens menu item is translated', function () {
    config(['jetstream.features' => [Features::api()]]);

    $panel = (new AppPanelProvider(app()))->panel(Panel::make());

    expect($panel->getUserMenuItems()['apiTokens']->getLabel())->toBe('Token API');
});

test('the Italian mail log translations are not in English', function () {
    expect(__('filament-maillog::filament-maillog.navigation.group'))->toBe('Registri')
        ->and(__('filament-maillog::filament-maillog.navigation.maillog.label'))->toBe('Registro email')
        ->and(__('filament-maillog::filament-maillog.column.from'))->toBe('Mittente')
        ->and(__('filament-maillog::filament-maillog.column.headers'))->toBe('Intestazioni')
        ->and(__('filament-maillog::filament-maillog.status.delivered'))->toBe('consegnato');
});
