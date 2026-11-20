<?php

use App\Enums\DepartmentKey;
use App\Filament\Exports\StorageLocationExporter;
use App\Filament\Imports\PosterImporter;
use App\Filament\Imports\StorageLocationImporter;
use App\Filament\Imports\TerritoryImporter;
use App\Filament\Resources\Posters\Pages\ManagePosters;
use App\Filament\Resources\StorageLocations\Pages\ManageStorageLocations;
use App\Models\Department;
use App\Models\Poster;
use App\Models\PosterStock;
use App\Models\StorageLocation;
use App\Models\User;
use Filament\Actions\Exports\ContentGenerators\CsvExportContentGenerator;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('local');
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // A member of the department, who can manage posters and storage places without a role
    $this->member = User::factory()->create(['locale' => 'it']);
    Department::for(DepartmentKey::PublicWitnessing)->members()->attach($this->member);
    $this->actingAs($this->member);
    App::setLocale('it');
});

/**
 * Run the export action of the page, as the user does, and read the rows of the CSV file it creates.
 *
 * @param  array<string, mixed>  $data  the options of the export window
 * @return list<list<string>>
 */
function exportRows(string $page, array $data = []): array
{
    Livewire::test($page)
        ->callAction(TestAction::make('export'), $data)
        ->assertHasNoFormErrors();

    $csv = implode('', iterator_to_array((new CsvExportContentGenerator)(Export::query()->latest('id')->firstOrFail()), false));
    // Without the byte order mark that Filament adds for the spreadsheets
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);

    return array_values(array_map(str_getcsv(...), array_filter(explode("\n", trim(str_replace("\r", '', $csv))))));
}

/**
 * Import the given rows with an importer, as the queue worker does.
 *
 * @param  class-string  $importer
 * @param  list<array<string, string>>  $rows
 */
function importRows(string $importer, array $rows, User $user): Import
{
    $import = Import::create([
        'file_name' => 'file.csv',
        'file_path' => 'file.csv',
        'importer' => $importer,
        'total_rows' => count($rows),
        'successful_rows' => 0,
        'user_id' => $user->id,
    ]);

    $columnMap = collect($importer::getColumns())->mapWithKeys(fn ($column): array => [$column->getName() => $column->getName()])->all();

    (new ImportCsv($import, $rows, $columnMap))->handle();

    return $import->refresh();
}

test('the posters are exported with a row for each storage place, like the import reads them', function () {
    $hall = StorageLocation::factory()->create(['name' => 'Sala del Regno']);
    $box = StorageLocation::factory()->create(['name' => 'Box']);
    Poster::factory()->inLocation($hall, 2)->inLocation($box, 3)->create(['code' => 'abc-01', 'title' => ['it' => 'Poster di esempio', 'en' => 'Example poster']]);
    Poster::factory()->create(['code' => 'abc-02', 'title' => ['it' => 'Senza deposito'], 'notes' => '=HYPERLINK("x")']);

    $rows = exportRows(ManagePosters::class, ['locale' => 'en']);

    expect($rows[0])->toBe(['Sigla', 'Titolo', 'Deposito', 'Copie', 'Note'])
        ->and(collect(array_slice($rows, 1))->sortBy(fn (array $row): string => $row[0].$row[2])->values()->all())->toBe([
            ['abc-01', 'Example poster', 'Box', '3', ''],
            ['abc-01', 'Example poster', 'Sala del Regno', '2', ''],
            // Without a translation in English, and with a cell that a spreadsheet would run as a formula
            ['abc-02', 'Senza deposito', '', '', '\'=HYPERLINK("x")'],
        ]);
});

test('the storage places are exported and imported again by their name', function () {
    StorageLocation::factory()->create(['name' => 'Sala del Regno', 'notes' => 'Armadio in fondo']);

    expect(exportRows(ManageStorageLocations::class))->toBe([
        ['Nome', 'Note'],
        ['Sala del Regno', 'Armadio in fondo'],
    ]);

    importRows(StorageLocationImporter::class, [
        ['name' => 'Sala del Regno', 'notes' => 'Armadio a destra'],
        ['name' => 'Box', 'notes' => ''],
    ], $this->member);

    expect(StorageLocation::query()->orderBy('name')->pluck('notes', 'name')->all())->toBe(['Box' => null, 'Sala del Regno' => 'Armadio a destra']);
});

test('importing needs to be allowed to create the records, exporting to see them', function (string $page, string $model) {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::findOrCreate('ViewAny:'.class_basename($model), 'web'));
    $this->actingAs($viewer);

    Livewire::test($page)
        ->assertActionHidden(TestAction::make('import'))
        ->assertActionVisible(TestAction::make('export'));

    $this->actingAs($this->member);

    Livewire::test($page)
        ->assertActionVisible(TestAction::make('import'))
        ->assertActionVisible(TestAction::make('export'));
})->with([
    'posters' => [ManagePosters::class, Poster::class],
    'storage places' => [ManageStorageLocations::class, StorageLocation::class],
]);

test('the cells that a spreadsheet would run as a formula are exported as text', function () {
    Poster::factory()->create(['code' => '-5', 'title' => ['it' => '=HYPERLINK("x")'], 'notes' => '@sala']);
    Poster::factory()->create(['code' => 'abc-01', 'title' => ['it' => '- Campagna speciale'], 'notes' => '+39 123']);

    $rows = array_slice(exportRows(ManagePosters::class, ['locale' => 'it']), 1);

    // A negative number is left as it is: a spreadsheet reads it as a number, not as a formula
    expect($rows)->toEqualCanonicalizing([
        ['-5', "'=HYPERLINK(\"x\")", '', '', "'@sala"],
        ['abc-01', "'- Campagna speciale", '', '', "'+39 123"],
    ]);
});

test('the export notification is in the language of the user who started it', function () {
    StorageLocation::factory()->create(['name' => 'Box']);
    App::setLocale('en');

    Livewire::test(ManageStorageLocations::class)->callAction(TestAction::make('export'));

    $export = Export::query()->latest('id')->firstOrFail();

    expect(StorageLocationExporter::getCompletedNotificationBody($export))->toBe('L\'esportazione è terminata: 1 riga esportata.');
});

/**
 * A storage place import whose storage place is created by another queue worker right after it was looked for.
 */
class ConcurrentStorageLocationImporter extends StorageLocationImporter
{
    public function resolveRecord(): StorageLocation
    {
        $record = parent::resolveRecord();

        StorageLocation::query()->create(['name' => $record->name, 'notes' => 'Altro']);

        return $record;
    }
}

test('a row whose record was created meanwhile by another chunk fails with a clear reason, keeping the others', function () {
    $import = importRows(ConcurrentStorageLocationImporter::class, [
        ['name' => 'Sala del Regno', 'notes' => 'Armadio a destra'],
    ], $this->member);

    expect($import->successful_rows)->toBe(0)
        ->and($import->failedRows()->sole()->validation_error)->toBe(__('import.errors.duplicate'))
        // The one created by the other chunk is left as it is
        ->and(StorageLocation::query()->where('name', 'Sala del Regno')->sole()->notes)->toBe('Altro');

    // Imported again, the row updates it
    $import = importRows(StorageLocationImporter::class, [
        ['name' => 'Sala del Regno', 'notes' => 'Armadio a destra'],
    ], $this->member);

    expect($import->successful_rows)->toBe(1)
        ->and(StorageLocation::query()->where('name', 'Sala del Regno')->sole()->notes)->toBe('Armadio a destra');
});

test('the imports of the same kind wait for each other, among all the users', function (string $importer) {
    $import = Import::create([
        'file_name' => 'file.csv',
        'file_path' => 'file.csv',
        'importer' => $importer,
        'total_rows' => 0,
        'successful_rows' => 0,
        'user_id' => $this->member->id,
    ]);

    $keys = collect($import->getImporter([], [])->getJobMiddleware())
        ->filter(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping)
        ->map(fn (WithoutOverlapping $middleware): string => $middleware->key)
        ->all();

    expect($keys)->toContain('imports:'.$importer, "filament:imports:{$import->getKey()}");
})->with([PosterImporter::class, StorageLocationImporter::class, TerritoryImporter::class]);

test('a poster is not saved when its copies fail to be saved', function () {
    $hall = StorageLocation::factory()->create(['name' => 'Sala del Regno']);
    $poster = Poster::factory()->create(['code' => 'abc-01', 'title' => ['it' => 'Vecchio titolo']]);

    // As if another writer saved the same copies at the same time
    PosterStock::creating(function (PosterStock $stock): void {
        DB::table('poster_stocks')->insert(['poster_id' => $stock->poster_id, 'storage_location_id' => $stock->storage_location_id, 'quantity' => 9, 'created_at' => now(), 'updated_at' => now()]);
    });

    $import = importRows(PosterImporter::class, [
        ['code' => 'abc-01', 'title' => 'Nuovo titolo', 'storage' => 'Sala del Regno', 'quantity' => '2', 'notes' => ''],
    ], $this->member);

    expect($import->successful_rows)->toBe(0)
        ->and($import->failedRows()->sole()->validation_error)->toBe(__('import.errors.duplicate'))
        ->and($poster->fresh()->title)->toBe('Vecchio titolo')
        ->and(PosterStock::query()->where('storage_location_id', $hall->id)->count())->toBe(0);
});
