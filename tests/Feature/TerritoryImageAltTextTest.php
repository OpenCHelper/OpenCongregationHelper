<?php

use App\Filament\Resources\Territories\Pages\CreateTerritory;
use App\Filament\Resources\Territories\Pages\EditTerritory;
use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->user = User::factory()->create(['can_login' => true]);
    foreach (['ViewAny:Territory', 'View:Territory', 'Create:Territory', 'Update:Territory'] as $permission) {
        $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->user);

    $this->zone = TerritoryZone::factory()->create(['name' => 'North']);
});

test('the image preview of a territory is described by the territory name', function () {
    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create([
        'name' => 'Via "Roma"',
        'image_path' => 'https://example.com/territory.png',
    ]);

    Livewire::test(EditTerritory::class, ['record' => $territory->getKey()])
        ->assertSeeHtml('<img src="https://example.com/territory.png" alt="Via &quot;Roma&quot;"');
});

test('the image preview of a territory without a name yet has a generic description', function () {
    Livewire::test(CreateTerritory::class)
        ->fillForm(['image_path' => 'https://example.com/territory.png'])
        ->assertSeeHtml('<img src="https://example.com/territory.png" alt="'.e(__('filament-resources.territory.image_alt')).'"');
});

test('the separator between the image URL and the image file is hidden from screen readers', function () {
    Livewire::test(CreateTerritory::class)
        ->assertSeeHtml('aria-hidden="true"')
        ->assertSee('— or —');

    expect(html_entity_decode(Livewire::test(CreateTerritory::class)->html()))
        ->toMatch('/aria-hidden="true"[^>]*>(?:(?!<\/div>).)*— or —/s');
});

test('the territory image of an assignment is described by the territory name', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($this->zone, 'territoryZone')->create([
            'name' => 'Via Roma',
            'image_path' => 'https://example.com/territory.png',
        ])->id,
        'assignee_id' => $this->user->id,
        'ended_at' => null,
    ]);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $assignment->getKey()])
        ->assertSeeHtml('alt="Via Roma"');
});

test('the generic description of a territory image is translated in every language of the application', function (string $locale) {
    app()->setLocale($locale);

    expect(__('filament-resources.territory.image_alt'))->not->toBe('filament-resources.territory.image_alt');
})->with(['en', 'es', 'it']);
