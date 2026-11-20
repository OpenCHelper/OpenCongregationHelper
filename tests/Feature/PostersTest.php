<?php

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // An overseer of the public witnessing department, who can fully manage the posters without a role
    $this->member = User::factory()->create();
    Department::for(DepartmentKey::PublicWitnessing)->members()->attach($this->member, ['role' => DepartmentRole::Overseer]);
    $this->actingAs($this->member);

    $this->hall = StorageLocation::factory()->create(['name' => 'Kingdom Hall']);
    $this->box = StorageLocation::factory()->create(['name' => 'Capobianco box']);
});

test('a poster can be created with its quantities in each location', function () {
    Livewire::test(ManagePosters::class)
        ->callAction(TestAction::make('create'), [
            'code' => 'mi-25',
            'title' => ['en' => 'Enjoy life forever!', 'it' => 'Vivi felice per sempre!', 'es' => null],
            'stocks' => [
                ['storage_location_id' => $this->hall->id, 'quantity' => 2],
                ['storage_location_id' => $this->box->id, 'quantity' => 5],
            ],
        ])
        ->assertHasNoFormErrors();

    $poster = Poster::query()->with('stocks')->sole();

    expect($poster)
        ->code->toBe('mi-25')
        ->and($poster->getTranslations('title'))->toEqual(['en' => 'Enjoy life forever!', 'it' => 'Vivi felice per sempre!'])
        ->and($poster->quantityIn($this->hall))->toBe(2)
        ->and($poster->quantityIn($this->box))->toBe(5)
        ->and($poster->totalQuantity())->toBe(7);
});

test('the code of a poster is unique and the quantities cannot be negative', function () {
    Poster::factory()->create(['code' => 'mi-25']);

    Livewire::test(ManagePosters::class)
        ->callAction(TestAction::make('create'), [
            'code' => 'mi-25',
            'title' => ['en' => 'Another poster'],
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

    $this->get(ManagePosters::getUrl())->assertOk()->assertSee(FakePosterSeeder::getStoragePlaces()['hall'])->assertSee('demo-01');
});

test('the demo posters are clearly not real, and their storage places are named in the language of the installation', function (string $locale, string $hall, string $title) {
    config(['app.locale' => $locale]);
    StorageLocation::query()->delete();

    $this->seed(FakePosterSeeder::class);

    App::setLocale($locale);

    expect(StorageLocation::query()->pluck('name')->all())->toContain($hall)
        ->and(Poster::query()->where('code', 'demo-01')->sole()->title)->toBe($title);
})->with([
    'English' => ['en', 'Kingdom Hall', 'Demo poster 1'],
    'Italian' => ['it', 'Sala del Regno', 'Poster di esempio 1'],
    'Spanish' => ['es', 'Salón del Reino', 'Póster de ejemplo 1'],
]);

test('the storage places are not called locations, a word left for the sites of the carts', function (string $locale, string $plural) {
    App::setLocale($locale);

    expect(StorageLocationResource::getPluralModelLabel())->toBe($plural)
        ->and(__('filament-resources.poster.action_manage_locations'))->toBe($plural);
})->with([
    ['en', 'Storage'],
    ['it', 'Depositi'],
    ['es', 'Depósitos'],
]);

test('the title of a poster follows the language of the user, and is found and sorted in it', function () {
    $kingdom = Poster::factory()->create(['title' => ['en' => 'What is God\'s Kingdom?', 'it' => 'Cos\'è il Regno di Dio?']]);
    $courses = Poster::factory()->create(['title' => ['en' => 'Free Bible courses', 'it' => 'Corsi biblici gratuiti']]);
    $english = Poster::factory()->create(['title' => ['en' => 'Does God care about you?']]);

    App::setLocale('it');

    expect($kingdom->fresh()->title)->toBe('Cos\'è il Regno di Dio?')
        ->and($english->fresh()->title)->toBe('Does God care about you?');

    Livewire::test(ManagePosters::class)
        ->assertCanSeeTableRecords([$courses, $kingdom], inOrder: true)
        ->searchTable('Kingdom')
        ->assertCanSeeTableRecords([$kingdom])
        ->assertCanNotSeeTableRecords([$courses, $english])
        // Whatever the case
        ->searchTable('kingdom')
        ->assertCanSeeTableRecords([$kingdom])
        ->assertCanNotSeeTableRecords([$courses, $english])
        ->searchTable('REGNO DI dio')
        ->assertCanSeeTableRecords([$kingdom])
        ->assertCanNotSeeTableRecords([$courses, $english]);
});

test('the form edits the title in every language', function () {
    $poster = Poster::factory()->create(['title' => ['en' => 'Free Bible courses', 'it' => 'Corsi biblici']]);

    Livewire::test(ManagePosters::class)
        ->mountAction(TestAction::make('edit')->table($poster))
        ->assertSchemaStateSet(['title' => ['en' => 'Free Bible courses', 'it' => 'Corsi biblici', 'es' => null]])
        ->fillForm(['title' => ['en' => 'Free Bible courses', 'it' => 'Corsi biblici gratuiti', 'es' => 'Cursos bíblicos gratuitos']])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($poster->fresh()->getTranslations('title'))->toEqual([
        'en' => 'Free Bible courses',
        'it' => 'Corsi biblici gratuiti',
        'es' => 'Cursos bíblicos gratuitos',
    ]);
});

test('the picture of a poster cannot be an SVG, which can carry scripts', function () {
    Storage::fake('public');

    Livewire::test(ManagePosters::class)
        ->mountAction(TestAction::make('create'))
        ->fillForm([
            'title' => [app()->getLocale() => 'Poster di esempio'],
            'image_path' => UploadedFile::fake()->createWithContent('poster.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['image_path']);

    Livewire::test(ManagePosters::class)
        ->mountAction(TestAction::make('create'))
        ->fillForm([
            'title' => [app()->getLocale() => 'Poster di esempio'],
            'image_path' => UploadedFile::fake()->image('poster.png'),
            'stocks' => [],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Poster::query()->sole()->image_path)->toEndWith('.png');
});

test('the picture of a poster is deleted from the disk when replaced, removed or with the poster', function () {
    Storage::fake('public');
    Storage::disk('public')->put('posters/old.png', 'old');
    Storage::disk('public')->put('posters/new.png', 'new');
    $poster = Poster::factory()->create(['image_path' => 'posters/old.png']);

    $poster->update(['image_path' => 'posters/new.png']);

    Storage::disk('public')->assertMissing('posters/old.png');
    Storage::disk('public')->assertExists('posters/new.png');

    $poster->update(['notes' => 'Something else']);

    Storage::disk('public')->assertExists('posters/new.png');

    $poster->delete();

    Storage::disk('public')->assertMissing('posters/new.png');
});

test('the picture of a poster is kept when the change is rolled back', function () {
    Storage::fake('public');
    Storage::disk('public')->put('posters/old.png', 'old');
    $poster = Poster::factory()->create(['image_path' => 'posters/old.png']);

    DB::beginTransaction();
    $poster->update(['image_path' => 'posters/new.png']);
    DB::rollBack();

    Storage::disk('public')->assertExists('posters/old.png');

    DB::beginTransaction();
    $poster->fresh()->delete();
    DB::rollBack();

    Storage::disk('public')->assertExists('posters/old.png');
    expect($poster->fresh()->image_path)->toBe('posters/old.png');
});
