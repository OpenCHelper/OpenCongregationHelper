<?php

use App\Enums\DepartmentKey;
use App\Enums\PublicationCategory;
use App\Filament\Exports\Concerns\ExportsInTheUserLocale;
use App\Filament\Exports\StorageLocationExporter;
use App\Filament\Imports\PosterImporter;
use App\Filament\Imports\PublicationImporter;
use App\Filament\Imports\StorageLocationImporter;
use App\Filament\Resources\Posters\Pages\ManagePosters;
use App\Filament\Resources\Publications\Pages\ManagePublications;
use App\Filament\Resources\StorageLocations\Pages\ManageStorageLocations;
use App\Models\Department;
use App\Models\Poster;
use App\Models\Publication;
use App\Models\StorageLocation;
use App\Models\User;
use App\Support\SpreadsheetFormula;
use Filament\Actions\Exports\ContentGenerators\CsvExportContentGenerator;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('local');
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // A member of both departments, who can manage posters, storage places and publications without a role
    $this->member = User::factory()->create(['locale' => 'it']);
    Department::for(DepartmentKey::PublicWitnessing)->members()->attach($this->member);
    Department::for(DepartmentKey::Publications)->members()->attach($this->member);
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

test('the publication list is exported with the name in the language of the user first and in the others last', function () {
    Publication::query()->delete();
    Publication::factory()->create([
        'name' => ['it' => 'Vivi felice per sempre!', 'en' => 'Enjoy Life Forever!'],
        'code' => 'lff',
        'category' => PublicationCategory::Books,
        'renewal_months' => null,
        'is_active' => false,
    ]);

    $rows = exportRows(ManagePublications::class);

    expect($rows)->toBe([
        ['Nome (Italiano)', 'Sigla', 'Categoria', 'Si rinnova ogni (mesi)', 'Disponibile', 'Nome (English)', 'Nome (Español)'],
        ['Vivi felice per sempre!', 'lff', 'Libri', '', 'no', 'Enjoy Life Forever!', ''],
    ]);
});

test('an exported publication list can be imported again, updating the publications by their code', function () {
    $watchtower = Publication::query()->where('code', 'w')->sole();

    $import = importRows(PublicationImporter::class, [
        // An existing publication: the empty names keep their translation
        ['name_it' => 'La Torre di Guardia (studio)', 'code' => 'w', 'category' => '', 'renewal_months' => '1', 'is_active' => 'no', 'name_en' => '', 'name_es' => ''],
        // A new one, with the category in another language
        ['name_it' => 'Vivi felice per sempre!', 'code' => 'lff', 'category' => 'Books', 'renewal_months' => '', 'is_active' => '', 'name_en' => 'Enjoy Life Forever!', 'name_es' => ''],
        // Rows that cannot be imported
        ['name_it' => '', 'code' => 'nameless', 'category' => '', 'renewal_months' => '', 'is_active' => '', 'name_en' => '', 'name_es' => ''],
        ['name_it' => 'Sconosciuta', 'code' => 'unknown', 'category' => 'Fumetti', 'renewal_months' => '', 'is_active' => '', 'name_en' => '', 'name_es' => ''],
    ], $this->member);

    $lff = Publication::query()->where('code', 'lff')->sole();

    expect($import->successful_rows)->toBe(2)
        ->and($watchtower->fresh()->getTranslations('name'))->toEqual(['en' => 'The Watchtower', 'it' => 'La Torre di Guardia (studio)', 'es' => 'La Atalaya'])
        ->and($watchtower->fresh())->category->toBe(PublicationCategory::Watchtower)->is_active->toBeFalse()->renewal_months->toBe(1)
        ->and($lff)->category->toBe(PublicationCategory::Books)->is_active->toBeTrue()->renewal_months->toBeNull()
        ->and($lff->getTranslations('name'))->toEqual(['it' => 'Vivi felice per sempre!', 'en' => 'Enjoy Life Forever!'])
        ->and(Publication::query()->whereIn('code', ['nameless', 'unknown'])->exists())->toBeFalse();
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
    'publications' => [ManagePublications::class, Publication::class],
    'storage places' => [ManageStorageLocations::class, StorageLocation::class],
]);

test('the cells that a spreadsheet would run as a formula are exported as text', function (mixed $value, mixed $expected) {
    $exporter = new class
    {
        use ExportsInTheUserLocale;
    };

    expect($exporter::escapeFormula($value))->toBe($expected);
})->with([
    'formula' => ['=1+1', "'=1+1"],
    'plus' => ['+39 123', "'+39 123"],
    'at' => ['@SUM(A1)', "'@SUM(A1)"],
    'negative number' => ['-3', '-3'],
    'text' => ['Box', 'Box'],
    'empty' => ['', ''],
    'number' => [3, 3],
]);

test('the quote added to a formula is removed only before a formula character', function (mixed $value, mixed $expected) {
    expect(SpreadsheetFormula::unescape($value))->toBe($expected);
})->with([
    'formula' => ["'=1+1", '=1+1'],
    'minus' => ["'- text", '- text'],
    'a real quote' => ["'tis the season", "'tis the season"],
    'only a quote' => ["'", "'"],
    'number' => [3, 3],
]);

test('the export notification is in the language of the user who started it', function () {
    StorageLocation::factory()->create(['name' => 'Box']);
    App::setLocale('en');

    Livewire::test(ManageStorageLocations::class)->callAction(TestAction::make('export'));

    $export = Export::query()->latest('id')->firstOrFail();

    expect(StorageLocationExporter::getCompletedNotificationBody($export))->toBe('L\'esportazione è terminata: 1 riga esportata.');
});

test('a cell exported as text, to keep a spreadsheet from running it, is imported again as it was', function () {
    $poster = Poster::factory()->create(['code' => null, 'title' => ['it' => '- Campagna speciale'], 'notes' => '@sala']);

    [$header, $row] = exportRows(ManagePosters::class, ['locale' => 'it']);

    expect($row)->toBe(['', "'- Campagna speciale", '', '', "'@sala"]);

    importRows(PosterImporter::class, [array_combine(['code', 'title', 'storage', 'quantity', 'notes'], $row)], $this->member);

    // Found again by its title, instead of creating another poster
    expect(Poster::query()->count())->toBe(1)
        ->and($poster->fresh()->title)->toBe('- Campagna speciale')
        ->and($poster->fresh()->notes)->toBe('@sala');
});
