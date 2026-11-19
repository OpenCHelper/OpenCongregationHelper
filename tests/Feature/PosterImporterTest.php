<?php

use App\Enums\DepartmentKey;
use App\Filament\Imports\PosterImporter;
use App\Filament\Imports\TerritoryImporter;
use App\Filament\Resources\Posters\Pages\ManagePosters;
use App\Models\Department;
use App\Models\Poster;
use App\Models\StorageLocation;
use App\Models\User;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // A member of the public witnessing department, who can manage the posters and their storage places
    $this->member = User::factory()->create();
    Department::for(DepartmentKey::PublicWitnessing)->members()->attach($this->member);
});

/**
 * Import the given rows as the queue worker does, keeping the columns named as in the importer.
 *
 * @param  list<array<string, string>>  $rows
 */
function runPosterImport(array $rows, User $user): Import
{
    $import = Import::create([
        'file_name' => 'posters.csv',
        'file_path' => 'posters.csv',
        'importer' => PosterImporter::class,
        'total_rows' => count($rows),
        'successful_rows' => 0,
        'user_id' => $user->id,
    ]);

    $columnMap = collect(PosterImporter::getColumns())
        ->mapWithKeys(fn ($column): array => [$column->getName() => $column->getName()])
        ->all();

    (new ImportCsv($import, $rows, $columnMap))->handle();

    return $import->refresh();
}

test('the rows of the same poster update a single poster, with its copies in each storage place', function () {
    $import = runPosterImport([
        ['code' => 'mi-25', 'title' => 'Enjoy life forever!', 'storage' => 'Kingdom Hall', 'quantity' => '2', 'notes' => 'New edition'],
        ['code' => 'mi-25', 'title' => 'Enjoy life forever!', 'storage' => 'Capobianco box', 'quantity' => '3', 'notes' => ''],
        ['code' => '', 'title' => 'Free Bible courses', 'storage' => 'Kingdom Hall', 'quantity' => '1', 'notes' => ''],
    ], $this->member);

    expect($import->successful_rows)->toBe(3)
        ->and(Poster::count())->toBe(2)
        ->and(StorageLocation::query()->orderBy('name')->pluck('name')->all())->toBe(['Capobianco box', 'Kingdom Hall']);

    $poster = Poster::query()->where('code', 'mi-25')->with('stocks')->sole();

    expect($poster->title)->toBe('Enjoy life forever!')
        // The empty notes of the second row keep the ones of the first
        ->and($poster->notes)->toBe('New edition')
        ->and($poster->quantityIn(StorageLocation::where('name', 'Kingdom Hall')->sole()))->toBe(2)
        ->and($poster->quantityIn(StorageLocation::where('name', 'Capobianco box')->sole()))->toBe(3);
});

test('importing again updates the quantities instead of duplicating the posters', function () {
    $hall = StorageLocation::factory()->create(['name' => 'Kingdom Hall']);
    $poster = Poster::factory()->inLocation($hall, 5)->create(['code' => 'mi-25', 'title' => 'Old title']);
    $untitled = Poster::factory()->create(['code' => null, 'title' => 'Free Bible courses']);

    runPosterImport([
        ['code' => 'mi-25', 'title' => 'Enjoy life forever!', 'storage' => 'Kingdom Hall', 'quantity' => '1', 'notes' => ''],
        ['code' => '', 'title' => 'Free Bible courses', 'storage' => 'Kingdom Hall', 'quantity' => '4', 'notes' => ''],
    ], $this->member);

    expect(Poster::count())->toBe(2)
        // In the language of the user who imported it
        ->and($poster->fresh()->getTranslation('title', $this->member->preferredLocale()))->toBe('Enjoy life forever!')
        ->and($poster->fresh()->load('stocks')->quantityIn($hall))->toBe(1)
        ->and($untitled->fresh()->load('stocks')->quantityIn($hall))->toBe(4);
});

test('the title is imported in the language of the user, keeping the other translations', function () {
    $this->member->forceFill(['locale' => 'it'])->save();
    $poster = Poster::factory()->create(['code' => null, 'title' => ['en' => 'Free Bible courses', 'it' => 'Corsi biblici']]);

    // Without a code, the poster is found by its title in any language
    runPosterImport([
        ['code' => '', 'title' => 'Free Bible courses', 'storage' => '', 'quantity' => '', 'notes' => 'Updated'],
    ], $this->member);

    expect(Poster::count())->toBe(1)
        ->and($poster->fresh()->notes)->toBe('Updated')
        ->and($poster->fresh()->getTranslations('title'))->toEqual(['en' => 'Free Bible courses', 'it' => 'Free Bible courses']);
});

test('a poster can be imported without storage place, but not copies without one', function () {
    $import = runPosterImport([
        ['code' => 'mi-25', 'title' => 'Enjoy life forever!', 'storage' => '', 'quantity' => '', 'notes' => ''],
        ['code' => 'mi-26', 'title' => 'Who controls the world?', 'storage' => '', 'quantity' => '3', 'notes' => ''],
        ['code' => 'mi-27', 'title' => 'Will suffering end?', 'storage' => 'Kingdom Hall', 'quantity' => '-1', 'notes' => ''],
    ], $this->member);

    expect($import->successful_rows)->toBe(1)
        ->and($import->getFailedRowsCount())->toBe(2)
        ->and(Poster::query()->pluck('code')->all())->toBe(['mi-25'])
        ->and(Poster::sole()->stocks()->exists())->toBeFalse();
});

test('the rows that the user is not allowed to import fail', function () {
    $reader = User::factory()->create();
    $reader->givePermissionTo(Permission::findOrCreate('Create:Poster', 'web'));

    $import = runPosterImport([
        ['code' => 'mi-25', 'title' => 'Enjoy life forever!', 'storage' => 'Kingdom Hall', 'quantity' => '2', 'notes' => ''],
    ], $reader);

    expect($import->successful_rows)->toBe(0)
        ->and(Poster::exists())->toBeFalse()
        ->and(StorageLocation::exists())->toBeFalse();
});

test('the posters page offers the import to whoever can create posters', function () {
    $this->actingAs($this->member);

    Livewire::test(ManagePosters::class)->assertActionVisible(TestAction::make('import'));

    $this->actingAs(tap(User::factory()->create(), fn (User $user) => $user->givePermissionTo(Permission::findOrCreate('ViewAny:Poster', 'web'))));

    Livewire::test(ManagePosters::class)->assertActionHidden(TestAction::make('import'));
});

test('the headers are recognised only in English, whatever the language of the user', function () {
    App::setLocale('it');

    $guesses = collect(PosterImporter::getColumns())
        ->mapWithKeys(fn ($column): array => [$column->getName() => $column->getGuesses()]);

    expect($guesses['code'])->toContain('code', 'symbol')->not->toContain('sigla')
        ->and($guesses['title'])->toContain('title')->not->toContain('titolo')
        ->and($guesses['storage'])->toContain('storage place')->not->toContain('deposito', 'dislocazione')
        ->and($guesses['quantity'])->toContain('copies', 'quantity')->not->toContain('copie', 'quantità')
        ->and($guesses['notes'])->toContain('notes')->not->toContain('notas');
});

test('the example file has the headers in English, recognised again when importing it', function (string $locale) {
    App::setLocale($locale);

    foreach (PosterImporter::getColumns() as $column) {
        expect(array_map(mb_strtolower(...), $column->getGuesses()))->toContain(mb_strtolower($column->getExampleHeader()))
            ->and($column->getExampleHeader())->toBe(__("import.poster.columns.{$column->getName()}", [], 'en'));
    }

    // The values of the examples stay in the language of the user: the same poster in two storage places
    expect(collect(PosterImporter::getColumns())->firstWhere(fn ($column): bool => $column->getName() === 'storage')->getExamples())
        ->toBe([__('import.poster.examples.storage'), __('import.poster.examples.other_storage')]);
})->with(['en', 'it', 'es']);

test('the territory headers are recognised only in English', function () {
    App::setLocale('it');

    $guesses = collect(TerritoryImporter::getColumns())
        ->mapWithKeys(fn ($column): array => [$column->getName() => $column->getGuesses()]);

    expect($guesses['name'])->toContain('name')->not->toContain('nome')
        ->and($guesses['description'])->toContain('description')->not->toContain('descrizione')
        ->and($guesses['territoryZone'])->toContain('zone')->not->toContain('zona');
});
