<?php

use App\Filament\Resources\Territories\Pages\ManageTerritoryAssignments;
use App\Filament\Resources\TerritoryZones\Pages\ManageZoneTerritories;
use App\Filament\Resources\Users\Pages\ManageUserAssignments;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    $this->actingAs($admin)->withSession(['locale' => 'it']);

    $this->zone = TerritoryZone::factory()->create(['name' => 'Centro']);
    $this->territory = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Via Roma']);
    $this->publisher = User::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);
});

test('relation pages have a translated title and breadcrumb', function (Closure $url, string $title, string $breadcrumb) {
    $this->get($url->call($this))
        ->assertOk()
        ->assertSeeInOrder(['<title>', $title, '</title>'], escape: false)
        ->assertSeeInOrder(['aria-current="page"', $breadcrumb, '</span>'], escape: false)
        ->assertDontSee('>Assignments<', escape: false)
        ->assertDontSee('>Territories<', escape: false);
})->with([
    'zone territories' => [
        fn () => ManageZoneTerritories::getUrl(['record' => $this->zone]),
        'Territori della zona Centro',
        'Territori',
    ],
    'territory assignments' => [
        fn () => ManageTerritoryAssignments::getUrl(['record' => $this->territory]),
        'Storico uscite di Via Roma',
        'Storico uscite',
    ],
    'user assignments' => [
        fn () => ManageUserAssignments::getUrl(['record' => $this->publisher]),
        'Assegnazioni di Mario Rossi',
        'Assegnazioni',
    ],
]);

test('the territories of a zone have a translated name column', function () {
    App::setLocale('it');

    Livewire::test(ManageZoneTerritories::class, ['record' => $this->zone->getRouteKey()])
        ->assertTableColumnExists('name', fn (TextColumn $column): bool => $column->getLabel() === 'Nome');
});

test('users are titled by their full name in their breadcrumbs', function () {
    $breadcrumbs = Livewire::test(ViewUser::class, ['record' => $this->publisher->getRouteKey()])
        ->instance()
        ->getBreadcrumbs();

    expect($breadcrumbs)->toContain('Mario Rossi');
});
