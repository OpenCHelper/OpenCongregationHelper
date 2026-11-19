<?php

use App\Enums\DepartmentKey;
use App\Filament\Imports\PosterImporter;
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
 * @param  array<string, mixed>  $options  e.g. the language of the titles (`locale`)
 */
function runPosterImport(array $rows, User $user, array $options = []): Import
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

    (new ImportCsv($import, $rows, $columnMap, $options))->handle();

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

test('the titles are saved in the language chosen in the import window, keeping the other ones', function () {
    $this->member->forceFill(['locale' => 'it'])->save();
    $poster = Poster::factory()->create(['code' => 'mi-25', 'title' => ['en' => 'Enjoy life forever!', 'it' => 'Vivi felice per sempre!']]);

    runPosterImport([
        ['code' => 'mi-25', 'title' => '¡Disfrute de la vida para siempre!', 'storage' => '', 'quantity' => '', 'notes' => ''],
    ], $this->member, ['locale' => 'es']);

    expect($poster->fresh()->getTranslations('title'))->toEqual([
        'en' => 'Enjoy life forever!',
        'it' => 'Vivi felice per sempre!',
        'es' => '¡Disfrute de la vida para siempre!',
    ]);
});

test('the language of the titles is chosen in the import window, the one of the user by default', function () {
    App::setLocale('it');

    $select = collect(PosterImporter::getOptionsFormComponents())->sole();

    expect($select->getName())->toBe('locale')
        ->and($select->getDefaultState())->toBe('it')
        ->and(array_keys($select->getOptions()))->toEqualCanonicalizing(array_keys(config('app.available_locales')))
        // An unknown language falls back to the one of the user
        ->and(PosterImporter::getTitleLocale(['locale' => 'xx']))->toBe('it')
        ->and(PosterImporter::getTitleLocale([]))->toBe('it');
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

test('the headers are recognised in the language of the user or in English, without listing every language', function (string $locale) {
    App::setLocale($locale);

    $guesses = collect(PosterImporter::getColumns())
        ->mapWithKeys(fn ($column): array => [$column->getName() => $column->getGuesses()]);

    foreach (['code', 'title', 'storage', 'quantity', 'notes'] as $name) {
        expect($guesses[$name])->toContain(mb_strtolower(__("import.poster.columns.{$name}")), mb_strtolower(__("import.poster.columns.{$name}", [], 'en')));
    }

    // The other languages are not listed, so that the guesses don't grow with every language added
    expect($guesses['title'])->not->toContain(...array_map(
        fn (string $other): string => mb_strtolower(__('import.poster.columns.title', [], $other)),
        array_values(array_diff(['it', 'es'], [$locale])),
    ));
})->with(['en', 'it', 'es']);

test('the example file has the headers in the language of the user, recognised again when importing it', function (string $locale) {
    App::setLocale($locale);

    foreach (PosterImporter::getColumns() as $column) {
        expect(array_map(mb_strtolower(...), $column->getGuesses()))->toContain(mb_strtolower($column->getExampleHeader()))
            ->and($column->getExampleHeader())->toBe(__("import.poster.columns.{$column->getName()}"));
    }

    // The same poster in two storage places
    expect(collect(PosterImporter::getColumns())->firstWhere(fn ($column): bool => $column->getName() === 'storage')->getExamples())
        ->toBe([__('import.poster.examples.storage'), __('import.poster.examples.other_storage')]);
})->with(['en', 'it', 'es']);
