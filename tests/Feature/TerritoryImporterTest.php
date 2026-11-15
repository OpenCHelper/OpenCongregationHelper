<?php

use App\Filament\Imports\TerritoryImporter;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Create a user with the given permissions, by default those needed to import territories,
 * update the existing ones and create the missing zones.
 *
 * @param  array<int, string>  $permissions
 * @param  array<string, mixed>  $attributes
 */
function territoryImportUser(array $permissions = ['Create:Territory', 'Update:Territory', 'Create:TerritoryZone'], array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function makeTerritoryImport(int $totalRows = 1, int $successfulRows = 0, ?User $user = null): Import
{
    return Import::create([
        'file_name' => 'territories.csv',
        'file_path' => 'territories.csv',
        'importer' => TerritoryImporter::class,
        'total_rows' => $totalRows,
        'successful_rows' => $successfulRows,
        'user_id' => ($user ?? territoryImportUser())->id,
    ]);
}

/**
 * @return array<string, string>
 */
function territoryImportColumnMap(): array
{
    return collect(TerritoryImporter::getColumns())
        ->mapWithKeys(fn ($column) => [$column->getName() => $column->getName()])
        ->all();
}

/**
 * @param  array<string, mixed>  $options
 */
function makeTerritoryImporter(array $options = [], ?User $user = null): TerritoryImporter
{
    $import = makeTerritoryImport(user: $user);

    return new TerritoryImporter($import, territoryImportColumnMap(), [
        'keep_image_links' => true,
        ...$options,
    ]);
}

/**
 * Process the given rows as the queued job of the import action does, which records the failed rows.
 *
 * @param  array<int, array<string, string>>  $rows
 * @param  array<string, mixed>  $options
 */
function runTerritoryImportJob(array $rows, User $user, array $options = []): Import
{
    $import = makeTerritoryImport(count($rows), user: $user);

    (new ImportCsv($import, $rows, territoryImportColumnMap(), [
        'keep_image_links' => true,
        ...$options,
    ]))->handle();

    return $import->refresh();
}

test('a new territory can be imported into an existing zone', function () {
    $zone = TerritoryZone::factory()->create(['name' => 'North']);

    makeTerritoryImporter()([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'North',
    ]);

    expect(Territory::where('name', 'T-1')->where('territory_zone_id', $zone->id)->exists())->toBeTrue();
});

test('a new zone is created when importing into a missing zone', function () {
    makeTerritoryImporter()([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'South',
    ]);

    $zone = TerritoryZone::where('name', 'South')->sole();

    expect(Territory::where('name', 'T-1')->where('territory_zone_id', $zone->id)->exists())->toBeTrue();
});

test('importing a territory that already exists in the same zone fails', function () {
    $zone = TerritoryZone::factory()->create(['name' => 'North']);
    Territory::factory()->for($zone, 'territoryZone')->create(['name' => 'T-1']);

    makeTerritoryImporter()([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'North',
    ]);
})->throws(RowImportFailedException::class);

test('a territory with the same name can be imported into a different zone', function () {
    $north = TerritoryZone::factory()->create(['name' => 'North']);
    TerritoryZone::factory()->create(['name' => 'South']);
    Territory::factory()->for($north, 'territoryZone')->create(['name' => 'T-1']);

    makeTerritoryImporter()([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'South',
    ]);

    expect(Territory::where('name', 'T-1')->count())->toBe(2);
});

test('updating existing territories matches both the name and the zone', function () {
    $north = TerritoryZone::factory()->create(['name' => 'North']);
    $south = TerritoryZone::factory()->create(['name' => 'South']);
    $northTerritory = Territory::factory()->for($north, 'territoryZone')->create(['name' => 'T-1', 'notes' => 'north']);
    $southTerritory = Territory::factory()->for($south, 'territoryZone')->create(['name' => 'T-1', 'notes' => 'south']);

    makeTerritoryImporter(['updateExisting' => true])([
        'name' => 'T-1',
        'notes' => 'updated',
        'is_commercial' => 'false',
        'territoryZone' => 'South',
    ]);

    expect($southTerritory->refresh()->notes)->toBe('updated')
        ->and($northTerritory->refresh()->notes)->toBe('north')
        ->and(Territory::where('name', 'T-1')->count())->toBe(2);
});

/**
 * A valid 1x1 PNG image.
 */
function pngImage(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

/**
 * @return array<string, string>
 */
function territoryRowWithImage(string $imageUrl): array
{
    return [
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'North',
        'image_path' => $imageUrl,
    ];
}

test('images are downloaded into the local disk with the extension of their MIME type', function () {
    Storage::fake('local');
    Http::fake(['93.184.216.34/*' => Http::response(pngImage(), 200, ['Content-Type' => 'image/png'])]);

    makeTerritoryImporter(['keep_image_links' => false])(territoryRowWithImage('https://93.184.216.34/images/territory'));

    $imagePath = Territory::where('name', 'T-1')->sole()->image_path;

    expect($imagePath)->toStartWith('territories/')->toEndWith('.png');
    Storage::disk('local')->assertExists($imagePath);
    expect(Storage::disk('local')->files('territories'))->toBe([$imagePath]);
});

test('image links are kept as they are when downloading is disabled', function () {
    Http::fake();

    makeTerritoryImporter(['keep_image_links' => true])(territoryRowWithImage('https://93.184.216.34/images/territory.png'));

    expect(Territory::where('name', 'T-1')->sole()->image_path)->toBe('https://93.184.216.34/images/territory.png');
    Http::assertNothingSent();
});

test('images on private or reserved hosts are not downloaded', function (string $imageUrl) {
    Storage::fake('local');
    Http::fake();

    expect(fn () => makeTerritoryImporter(['keep_image_links' => false])(territoryRowWithImage($imageUrl)))
        ->toThrow(RowImportFailedException::class);

    Http::assertNothingSent();
    expect(Territory::count())->toBe(0);
})->with([
    'loopback' => 'http://127.0.0.1/image.png',
    'localhost' => 'http://localhost/image.png',
    'private network' => 'http://192.168.1.10/image.png',
    'link-local (cloud metadata)' => 'http://169.254.169.254/latest/meta-data',
]);

test('invalid image downloads are rejected and deleted', function (int $status, string $body) {
    Storage::fake('local');
    Http::fake(['93.184.216.34/*' => Http::response($body, $status)]);

    expect(fn () => makeTerritoryImporter(['keep_image_links' => false])(territoryRowWithImage('https://93.184.216.34/image.png')))
        ->toThrow(RowImportFailedException::class);

    expect(Storage::disk('local')->files('territories'))->toBe([])
        ->and(Territory::count())->toBe(0);
})->with([
    'not an image' => [200, '<html><body>Not an image</body></html>'],
    'HTTP error' => [404, 'Not found'],
    'larger than 10 MB' => fn () => [200, pngImage().str_repeat('0', 10 * 1024 * 1024)],
]);

test('the completed notification body is in the language of the user who started the import', function (string $locale, int $totalRows, int $successfulRows, string $body) {
    app()->setLocale('en');

    $import = makeTerritoryImport($totalRows, $successfulRows, User::factory()->create(['locale' => $locale]));

    expect(TerritoryImporter::getCompletedNotificationBody($import))->toBe($body)
        ->and(app()->getLocale())->toBe('en');
})->with([
    'it, one row' => ['it', 1, 1, "L'importazione dei territori è stata completata: 1 riga importata."],
    'it, many rows' => ['it', 3, 3, "L'importazione dei territori è stata completata: 3 righe importate."],
    'it, one failed row' => ['it', 3, 2, "L'importazione dei territori è stata completata: 2 righe importate. 1 riga non è stata importata."],
    'it, many failed rows' => ['it', 3, 1, "L'importazione dei territori è stata completata: 1 riga importata. 2 righe non sono state importate."],
    'en, one row' => ['en', 1, 1, 'Your territory import has completed: 1 row imported.'],
    'en, many rows' => ['en', 2, 2, 'Your territory import has completed: 2 rows imported.'],
    'en, one failed row' => ['en', 3, 2, 'Your territory import has completed: 2 rows imported. 1 row failed to import.'],
    'en, many failed rows' => ['en', 2, 0, 'Your territory import has completed: 0 rows imported. 2 rows failed to import.'],
    'es, one row' => ['es', 1, 1, 'La importación de territorios se ha completado: 1 fila importada.'],
    'es, many rows' => ['es', 2, 2, 'La importación de territorios se ha completado: 2 filas importadas.'],
    'es, one failed row' => ['es', 2, 1, 'La importación de territorios se ha completado: 1 fila importada. 1 fila no se pudo importar.'],
    'es, many failed rows' => ['es', 3, 1, 'La importación de territorios se ha completado: 1 fila importada. 2 filas no se pudieron importar.'],
]);

test('the completed notification is in the language of the user rather than the default one of the application', function () {
    config(['app.locale' => 'it']);
    app()->setLocale('it');

    $import = makeTerritoryImport(3, 2, User::factory()->create(['locale' => 'en']));

    $notification = TerritoryImporter::modifyCompletedNotification(
        Notification::make()->actions([Action::make('downloadFailedRowsCsv')->label('Scarica')]),
        $import,
    );

    expect(TerritoryImporter::getCompletedNotificationTitle($import))->toBe('Import completed')
        ->and(TerritoryImporter::getCompletedNotificationBody($import))->toBe('Your territory import has completed: 2 rows imported. 1 row failed to import.')
        ->and($notification->getActions()[0]->getLabel())->toBe('Download information about the failed row')
        ->and(app()->getLocale())->toBe('it');
});

test('the completed notification falls back to the default language of the application for users without one', function () {
    config(['app.locale' => 'es']);
    app()->setLocale('es');

    $import = makeTerritoryImport(1, 1, User::factory()->create(['locale' => null]));

    expect(TerritoryImporter::getCompletedNotificationTitle($import))->toBe('Importación completada')
        ->and(TerritoryImporter::getCompletedNotificationBody($import))->toBe('La importación de territorios se ha completado: 1 fila importada.');
});

test('the reason of a duplicate row is in the language of the user who started the import', function (string $locale, string $reason) {
    app()->setLocale('en');
    $zone = TerritoryZone::factory()->create(['name' => 'North']);
    Territory::factory()->for($zone, 'territoryZone')->create(['name' => 'T-1']);

    expect(fn () => makeTerritoryImporter(user: User::factory()->create(['locale' => $locale]))([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'North',
    ]))->toThrow(RowImportFailedException::class, $reason);

    expect(app()->getLocale())->toBe('en');
})->with([
    'it' => ['it', 'Esiste già un territorio chiamato "T-1" nella zona "North".'],
    'en' => ['en', 'A territory named "T-1" already exists in the zone "North".'],
    'es' => ['es', 'Ya existe un territorio llamado "T-1" en la zona "North".'],
]);

test('the validation errors of a row are in the language of the user who started the import', function () {
    app()->setLocale('en');

    expect(fn () => makeTerritoryImporter(user: User::factory()->create(['locale' => 'it']))([
        'name' => '',
        'is_commercial' => 'false',
        'territoryZone' => 'North',
    ]))->toThrow(ValidationException::class, 'Il campo nome è obbligatorio.');
});

test('the import action is hidden and cannot be mounted for users who cannot create territories', function () {
    Filament::setCurrentPanel('app');
    User::factory()->create(['id' => 1]);

    $this->actingAs(territoryImportUser(['View:Territory', 'ViewAny:Territory', 'View:TerritoryZone', 'ViewAny:TerritoryZone']));

    Livewire::test(ListTerritories::class)
        ->assertActionHidden('import')
        ->mountAction('import')
        ->assertActionNotMounted('import');
});

test('super administrators can import territories', function () {
    Filament::setCurrentPanel('app');
    User::factory()->create(['id' => 1]);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super_admin', 'web'));
    $this->actingAs($superAdmin);

    Livewire::test(ListTerritories::class)
        ->assertActionVisible('import')
        ->mountAction('import')
        ->assertActionMounted('import');
});

test('existing territories are not updated for users who cannot update them', function () {
    $zone = TerritoryZone::factory()->create(['name' => 'North']);
    $territory = Territory::factory()->for($zone, 'territoryZone')->create([
        'name' => 'T-1',
        'notes' => 'original',
        'is_commercial' => false,
    ]);

    $import = runTerritoryImportJob([
        ['name' => 'T-1', 'notes' => 'overwritten', 'is_commercial' => 'true', 'territoryZone' => 'North'],
        ['name' => 'T-2', 'notes' => 'new', 'is_commercial' => 'false', 'territoryZone' => 'North'],
    ], territoryImportUser(['Create:Territory'], ['locale' => 'en']), ['updateExisting' => true]);

    expect($territory->refresh())
        ->notes->toBe('original')
        ->is_commercial->toBeFalse()
        ->and(Territory::where('name', 'T-2')->where('territory_zone_id', $zone->id)->exists())->toBeTrue()
        ->and($import->successful_rows)->toBe(1)
        ->and($import->failedRows()->sole())
        ->data->toMatchArray(['name' => 'T-1'])
        ->validation_error->toBe('You are not allowed to update the territory "T-1" of the zone "North".');
});

test('a missing zone is not created for users who cannot create zones', function (string $locale, string $reason) {
    app()->setLocale('en');

    expect(fn () => makeTerritoryImporter(user: territoryImportUser(['Create:Territory'], ['locale' => $locale]))([
        'name' => 'T-1',
        'is_commercial' => 'false',
        'territoryZone' => 'South',
    ]))->toThrow(RowImportFailedException::class, $reason);

    expect(TerritoryZone::where('name', 'South')->exists())->toBeFalse()
        ->and(Territory::count())->toBe(0);
})->with([
    'it' => ['it', 'La zona "South" non esiste e non hai il permesso di crearla.'],
    'en' => ['en', 'The zone "South" does not exist and you are not allowed to create it.'],
    'es' => ['es', 'La zona "South" no existe y no tienes permiso para crearla.'],
]);
