<?php

use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Resources\Posters\Pages\ManagePosters;
use App\Filament\Resources\PublicationRequests\Pages\ManagePublicationRequests;
use App\Filament\Resources\PublicationSubscribers\Pages\ManagePublicationSubscribers;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Filament\Resources\TerritoryAssignments\Pages\ManageTerritoryAssignments;
use App\Filament\Resources\TerritoryZones\Pages\ListTerritoryZones;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Publication;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    $this->actingAs($admin);
});

test('secondary columns can be toggled, keeping their default visibility', function (string $page, array $shownColumns, array $hiddenColumns) {
    $component = Livewire::test($page);
    $table = $component->instance()->getTable();

    foreach ([...$shownColumns, ...$hiddenColumns] as $column) {
        expect($table->getColumn($column)->isToggleable())->toBeTrue("{$column} should be toggleable");
    }

    foreach ($shownColumns as $column) {
        $component->assertCanRenderTableColumn($column);
    }

    foreach ($hiddenColumns as $column) {
        $component->assertCanNotRenderTableColumn($column);
    }

    $component->toggleAllTableColumns();

    foreach ($hiddenColumns as $column) {
        $component->assertCanRenderTableColumn($column);
    }
})->with([
    'campaigns' => [ListCampaigns::class, ['short_name'], ['creator.full_name']],
    'posters' => [ManagePosters::class, ['code'], []],
    'publication requests' => [ManagePublicationRequests::class, ['requested_at', 'renewal'], []],
    'roles' => [ListRoles::class, [], ['guard_name', 'updated_at']],
    'tags' => [ManageTags::class, ['slug'], []],
    'territories' => [ListTerritories::class, ['tags'], []],
    'territory assignments' => [ManageTerritoryAssignments::class, ['campaign.name'], []],
    'territory zones' => [ListTerritoryZones::class, ['tags'], []],
    'users' => [ListUsers::class, ['email'], []],
]);

test('the main columns of a table cannot be toggled', function (string $page, array $columns) {
    $table = Livewire::test($page)->instance()->getTable();

    foreach ($columns as $column) {
        expect($table->getColumn($column)->isToggleable())->toBeFalse("{$column} should not be toggleable");
    }
})->with([
    'campaigns' => [ListCampaigns::class, ['name', 'campaign_status']],
    'posters' => [ManagePosters::class, ['title', 'total_quantity']],
    'publication requests' => [ManagePublicationRequests::class, ['user.full_name', 'publication.name', 'quantity', 'status']],
    'territories' => [ListTerritories::class, ['name', 'territoryZone.name']],
    'territory assignments' => [ManageTerritoryAssignments::class, ['territory.name', 'started_at', 'ended_at']],
    'users' => [ListUsers::class, ['first_name', 'last_name']],
]);

test('the columns of the publications received can be toggled', function () {
    $table = Livewire::test(ManagePublicationSubscribers::class)->instance()->getTable();

    expect($table->getColumn('user.full_name')->isToggleable())->toBeFalse();

    Publication::offeredRenewable()->each(
        fn (Publication $publication) => expect($table->getColumn("publication_{$publication->getKey()}")->isToggleable())->toBeTrue(),
    );
});

test('the email in the card of a user follows its column', function () {
    $user = User::factory()->create(['email' => 'card-user@example.com']);

    $component = Livewire::test(ListUsers::class);
    $card = fn (): string => $component->instance()->getTable()->getColumn('card')->record($user)->toHtml();

    expect($card())->toContain('card-user@example.com');

    // Hides the email along with the other secondary columns
    $component->toggleAllTableColumns(false);

    expect($card())->not->toContain('card-user@example.com');
});
