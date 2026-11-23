<?php

use App\Filament\Resources\Territories\Actions\DownloadTerritoryAssignmentRecordAction;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Support\S13TemplateStructure;
use App\Support\TerritoryAssignmentRecord;
use App\Support\TerritoryAssignmentRecordPdf;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->zone = TerritoryZone::factory()->create();

    // The blank template stands in for the official form
    config(['s13.expected_structure' => S13TemplateStructure::fromPdf(blankS13Template())]);
});

/**
 * A blank A4 PDF, standing in for the official S-13 form.
 */
function blankS13Template(): string
{
    $pdf = new Fpdi;
    $pdf->AddPage();

    return $pdf->Output('S');
}

/**
 * The number of pages of a PDF.
 */
function pdfPageCount(string $contents): int
{
    return (new Fpdi)->setSourceFile(StreamReader::createByString($contents));
}

/**
 * Log in as a user with the given permissions, and the given role if any.
 *
 * @param  array<int, string>  $permissions
 */
function actingAsS13User(array $permissions, ?string $role = null): User
{
    $user = User::factory()->create(['can_login' => true]);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    if ($role !== null) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }

    test()->actingAs($user);

    return $user;
}

test('a territory assigned more times than the columns of a line shows only its latest assignments', function () {
    $assignments = array_map(fn (int $month): array => [
        'assignee' => "Assignee {$month}",
        'assigned_at' => Carbon::create(2025, $month, 1),
        'completed_at' => null,
    ], range(9, 12));
    array_unshift($assignments, ['assignee' => 'Oldest', 'assigned_at' => Carbon::create(2025, 9, 1), 'completed_at' => null]);

    $lines = TerritoryAssignmentRecordPdf::lines([
        ['territory' => '1', 'last_completed_at' => Carbon::parse('2025-05-01'), 'assignments' => $assignments],
        ['territory' => '2', 'last_completed_at' => null, 'assignments' => []],
    ], 4);

    expect($lines->pluck('territory')->all())->toBe(['1', '2'])
        ->and(array_column($lines[0]['assignments'], 'assignee'))->toBe(['Assignee 9', 'Assignee 10', 'Assignee 11', 'Assignee 12'])
        ->and($lines[0]['last_completed_at']?->toDateString())->toBe('2025-05-01')
        ->and($lines[1]['assignments'])->toBe([]);
});

test('the record takes a page of the template for each page of rows', function () {
    TerritoryAssignmentRecordPdf::storeTemplate(blankS13Template());

    expect(pdfPageCount(TerritoryAssignmentRecordPdf::render(2026)))->toBe(1);

    $territories = Territory::factory()->count(21)->for($this->zone, 'territoryZone')->create();
    TerritoryAssignment::factory()->create([
        'territory_id' => $territories->first()->id,
        'assignee_id' => User::factory()->create(['first_name' => 'Jöhn', 'last_name' => 'Doè'])->id,
        'started_at' => '2025-10-01',
        'ended_at' => '2025-11-01',
    ]);

    expect(pdfPageCount(TerritoryAssignmentRecordPdf::render(2026)))->toBe(2);
});

test('texts too long for their boxes are wrapped, shrunk and cut', function () {
    TerritoryAssignmentRecordPdf::storeTemplate(blankS13Template());

    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => str_repeat('Supercalifragilistic ', 10)]);
    TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => User::factory()->create(['first_name' => str_repeat('Bartholomew', 5), 'last_name' => 'Doe'])->id,
        'started_at' => '2025-10-01',
        'ended_at' => null,
    ]);

    expect(pdfPageCount(TerritoryAssignmentRecordPdf::render(2026)))->toBe(1);
});

test('non-Latin names are written as they are', function () {
    TerritoryAssignmentRecordPdf::storeTemplate(blankS13Template());

    $territory = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => 'Ζώνη Αθήνα']);
    TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => User::factory()->create(['first_name' => 'Москва', 'last_name' => 'Иванов'])->id,
        'started_at' => '2025-10-01',
        'ended_at' => null,
    ]);

    $page = (new PdfReader(new PdfParser(StreamReader::createByString(TerritoryAssignmentRecordPdf::render(2026)))))
        ->getPage(1)
        ->getContentStream();

    // The text of a Unicode font is written as UTF-16 (big-endian) characters
    expect($page)->toContain(mb_convert_encoding('Ζώνη', 'UTF-16BE', 'UTF-8'))
        ->toContain(mb_convert_encoding('Москва', 'UTF-16BE', 'UTF-8'))
        ->toContain(mb_convert_encoding('Иванов', 'UTF-16BE', 'UTF-8'))
        ->not->toContain('????');
});

test('only PDF files are valid templates', function () {
    expect(TerritoryAssignmentRecordPdf::isValidTemplate(blankS13Template()))->toBeTrue()
        ->and(TerritoryAssignmentRecordPdf::isValidTemplate('not a PDF'))->toBeFalse();
});

test('super administrators upload the template and download the record', function () {
    actingAsS13User(['ViewAny:Territory', 'ViewAny:TerritoryAssignment'], 'super_admin');

    Livewire::test(ListTerritories::class)
        ->callAction(DownloadTerritoryAssignmentRecordAction::getDefaultName(), [
            'service_year' => TerritoryAssignmentRecord::serviceYearOf(),
            'template' => UploadedFile::fake()->createWithContent('S-13_I.pdf', blankS13Template()),
        ])
        ->assertHasNoFormErrors()
        ->assertFileDownloaded(TerritoryAssignmentRecordPdf::fileName(TerritoryAssignmentRecord::serviceYearOf()));

    expect(TerritoryAssignmentRecordPdf::hasTemplate())->toBeTrue();
});

test('a file that is not a PDF is refused as template', function () {
    actingAsS13User(['ViewAny:Territory', 'ViewAny:TerritoryAssignment'], 'super_admin');

    Livewire::test(ListTerritories::class)
        ->callAction(DownloadTerritoryAssignmentRecordAction::getDefaultName(), [
            'service_year' => TerritoryAssignmentRecord::serviceYearOf(),
            'template' => UploadedFile::fake()->createWithContent('S-13_I.pdf', 'not a PDF'),
        ])
        ->assertHasFormErrors(['template']);

    expect(TerritoryAssignmentRecordPdf::hasTemplate())->toBeFalse();
});

test('other users download the record once the template is uploaded', function () {
    actingAsS13User(['ViewAny:Territory', 'ViewAny:TerritoryAssignment']);

    Livewire::test(ListTerritories::class)
        ->assertActionVisible(DownloadTerritoryAssignmentRecordAction::getDefaultName())
        ->callAction(DownloadTerritoryAssignmentRecordAction::getDefaultName(), ['service_year' => TerritoryAssignmentRecord::serviceYearOf()])
        ->assertNotified(__('s13.template.missing_heading'));

    TerritoryAssignmentRecordPdf::storeTemplate(blankS13Template());

    Livewire::test(ListTerritories::class)
        ->callAction(DownloadTerritoryAssignmentRecordAction::getDefaultName(), ['service_year' => TerritoryAssignmentRecord::serviceYearOf()])
        ->assertFileDownloaded(TerritoryAssignmentRecordPdf::fileName(TerritoryAssignmentRecord::serviceYearOf()));
});

test('users who cannot see the assignments cannot download the record', function () {
    actingAsS13User(['ViewAny:Territory']);

    Livewire::test(ListTerritories::class)
        ->assertActionHidden(DownloadTerritoryAssignmentRecordAction::getDefaultName());
});

test('the service years go from the one of the oldest assignment to the current one', function () {
    $this->travelTo('2026-11-21');

    TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($this->zone, 'territoryZone')->create()->id,
        'assignee_id' => User::factory()->create()->id,
        'started_at' => '2024-09-15',
        'ended_at' => '2024-10-01',
    ]);

    expect(array_keys(DownloadTerritoryAssignmentRecordAction::serviceYearOptions()))->toBe([2027, 2026, 2025]);
});
