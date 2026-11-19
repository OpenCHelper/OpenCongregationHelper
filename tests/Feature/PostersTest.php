<?php

use App\Enums\DepartmentKey;
use App\Filament\Resources\Posters\Pages\ManagePosters;
use App\Filament\Resources\StorageLocations\Pages\ManageStorageLocations;
use App\Filament\Resources\StorageLocations\StorageLocationResource;
use App\Models\Department;
use App\Models\Poster;
use App\Models\StorageLocation;
use App\Models\User;
use Database\Seeders\FakePosterSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // A member of the public witnessing department, who can manage the posters without a role
    $this->member = User::factory()->create();
    Department::for(DepartmentKey::PublicWitnessing)->members()->attach($this->member);
    $this->actingAs($this->member);

    $this->hall = StorageLocation::factory()->create(['name' => 'Kingdom Hall']);
    $this->box = StorageLocation::factory()->create(['name' => 'Capobianco box']);
});

test('a poster can be created with its quantities in each location', function () {
    Livewire::test(ManagePosters::class)
        ->callAction(TestAction::make('create'), [
            'code' => 'mi-25',
            'title' => 'Enjoy life forever!',
            'stocks' => [
                ['storage_location_id' => $this->hall->id, 'quantity' => 2],
                ['storage_location_id' => $this->box->id, 'quantity' => 5],
            ],
        ])
        ->assertHasNoFormErrors();

    $poster = Poster::query()->with('stocks')->sole();

    expect($poster)
        ->code->toBe('mi-25')
        ->title->toBe('Enjoy life forever!')
        ->and($poster->quantityIn($this->hall))->toBe(2)
        ->and($poster->quantityIn($this->box))->toBe(5)
        ->and($poster->totalQuantity())->toBe(7);
});

test('the code of a poster is unique and the quantities cannot be negative', function () {
    Poster::factory()->create(['code' => 'mi-25']);

    Livewire::test(ManagePosters::class)
        ->callAction(TestAction::make('create'), [
            'code' => 'mi-25',
            'title' => 'Another poster',
            'stocks' => [
                ['storage_location_id' => $this->hall->id, 'quantity' => -1],
            ],
        ])
        ->assertHasFormErrors(['code' => 'unique', 'stocks.0.quantity']);
});

test('the quantities of a poster can be changed', function () {
    $poster = Poster::factory()->inLocation($this->hall, 2)->create();

    Livewire::test(ManagePosters::class)
        ->callAction(TestAction::make('edit')->table($poster), [
            'stocks' => [
                ['storage_location_id' => $this->hall->id, 'quantity' => 1],
                ['storage_location_id' => $this->box->id, 'quantity' => 3],
            ],
        ])
        ->assertHasNoFormErrors();

    $poster->load('stocks');

    expect($poster->quantityIn($this->hall))->toBe(1)
        ->and($poster->quantityIn($this->box))->toBe(3);
});

test('the table shows a column with the copies of each location and their totals', function () {
    $first = Poster::factory()->inLocation($this->hall, 2)->inLocation($this->box, 3)->create();
    $second = Poster::factory()->inLocation($this->box, 4)->create();

    Livewire::test(ManagePosters::class)
        ->assertTableColumnExists("location_{$this->hall->id}")
        ->assertTableColumnExists("location_{$this->box->id}")
        ->assertTableColumnStateSet("location_{$this->hall->id}", 2, $first)
        ->assertTableColumnStateSet("location_{$this->hall->id}", 0, $second)
        ->assertTableColumnStateSet('total_quantity', 5, $first)
        ->assertTableColumnSummarySet("location_{$this->hall->id}", 'total', 2)
        ->assertTableColumnSummarySet("location_{$this->box->id}", 'total', 7)
        ->assertTableColumnSummarySet('total_quantity', 'total', 9);
});

test('the posters can be filtered by location and availability', function () {
    $inHall = Poster::factory()->inLocation($this->hall, 2)->create();
    $inBox = Poster::factory()->inLocation($this->box, 1)->create();
    $outOfStock = Poster::factory()->inLocation($this->hall, 0)->create();

    Livewire::test(ManagePosters::class)
        ->filterTable('location', $this->hall->id)
        ->assertCanSeeTableRecords([$inHall])
        ->assertCanNotSeeTableRecords([$inBox, $outOfStock])
        ->resetTableFilters()
        ->filterTable('available', false)
        ->assertCanSeeTableRecords([$outOfStock])
        ->assertCanNotSeeTableRecords([$inHall, $inBox]);
});

test('the locations list their posters and copies, and deleting one deletes its quantities', function () {
    $poster = Poster::factory()->inLocation($this->hall, 2)->inLocation($this->box, 3)->create();

    Livewire::test(ManageStorageLocations::class)
        ->assertTableColumnStateSet('poster_stocks_sum_quantity', 3, $this->box)
        ->callAction(TestAction::make('delete')->table($this->box));

    expect($poster->stocks()->pluck('storage_location_id')->all())->toBe([$this->hall->id]);
});

test('users outside the department without a role cannot open the posters', function () {
    $this->actingAs(User::factory()->create());

    $this->get(ManagePosters::getUrl())->assertForbidden();
    $this->get(ManageStorageLocations::getUrl())->assertForbidden();
});

test('the fake poster seeder creates the posters in two locations', function () {
    StorageLocation::query()->delete();

    $this->seed(FakePosterSeeder::class);
    $this->seed(FakePosterSeeder::class);

    expect(StorageLocation::count())->toBe(2)
        ->and(Poster::count())->toBe(count(FakePosterSeeder::POSTERS));

    $this->get(ManagePosters::getUrl())->assertOk()->assertSee('Kingdom Hall')->assertSee('mp-01');
});

test('the storage places are not called locations, a word left for the sites of the carts', function (string $locale, string $plural) {
    App::setLocale($locale);

    expect(StorageLocationResource::getPluralModelLabel())->toBe($plural)
        ->and(__('filament-resources.poster.action_manage_locations'))->toBe($plural);
})->with([
    ['en', 'Storage'],
    ['it', 'Depositi'],
    ['es', 'Depósitos'],
]);
