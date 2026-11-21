<?php

use App\Filament\Resources\Territories\Actions\DownloadTerritoryAssignmentRecordAction;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Models\User;
use App\Support\S13TemplateStructure;
use App\Support\TerritoryAssignmentRecord;
use App\Support\TerritoryAssignmentRecordPdf;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);
});

/**
 * A one-page A4 PDF form with a field "Terr" and a field "Row1" whose kids are "Name" and "Date", and a table of a
 * vertical line (at 10 mm from the left) and two horizontal ones (at 20 and 30 mm from the top).
 */
function s13FormPdf(): string
{
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R /AcroForm << /Fields [4 0 R 5 0 R] >> >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Annots [4 0 R 6 0 R 7 0 R] /Contents 8 0 R >>',
        '<< /FT /Tx /T (Terr) /Subtype /Widget /Rect [10 10 50 20] /P 3 0 R >>',
        '<< /T <FEFF0052006F00770031> /Kids [6 0 R 7 0 R] >>',
        '<< /FT /Tx /T (Name) /Parent 5 0 R /Subtype /Widget /Rect [10 30 50 40] /P 3 0 R >>',
        '<< /FT /Tx /T (Date) /Parent 5 0 R /Subtype /Widget /Rect [10 50 50 60] /P 3 0 R >>',
        (function (): string {
            $content = "0 g\n28.1 700 0.5 100 re\nf*\n20 785.05 200 0.5 re\nf\n20 756.7 200 0.5 re f\n20 600 100 50 re f\n";

            return '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
        })(),
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";

    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

function actingAsS13SuperAdmin(): void
{
    $user = User::factory()->create(['can_login' => true]);
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:Territory', 'web'), Permission::findOrCreate('ViewAny:TerritoryAssignment', 'web'));
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));

    test()->actingAs($user);
}

test('the structure of a template lists its pages, page size and form fields', function () {
    expect(S13TemplateStructure::fromPdf(s13FormPdf()))->toBe([
        'pages' => 1,
        'width' => 210.0,
        'height' => 297.0,
        'fields' => ['Row1.Date', 'Row1.Name', 'Terr'],
        'grid' => ['columns' => [10.0], 'rows' => [20.0, 30.0]],
    ]);
});

test('a template is not compared while the expected structure is unknown', function () {
    config(['s13.expected_structure' => null]);

    expect(S13TemplateStructure::differences(S13TemplateStructure::fromPdf(s13FormPdf())))->toBe([]);
});

test('the differences from the expected structure are described', function () {
    config(['s13.expected_structure' => [
        'pages' => 2,
        'width' => 210,
        'height' => 297,
        'fields' => ['Row1.Date', 'Terr', 'Year'],
        'grid' => ['columns' => [10.0], 'rows' => [20.0, 31.0]],
    ]]);

    expect(S13TemplateStructure::differences(S13TemplateStructure::fromPdf(s13FormPdf())))->toBe([
        __('s13.structure.pages', ['expected' => 2, 'actual' => 1]),
        __('s13.structure.missing_fields', ['fields' => 'Year']),
        __('s13.structure.added_fields', ['fields' => 'Row1.Name']),
        __('s13.structure.grid'),
    ]);

    config(['s13.expected_structure' => S13TemplateStructure::fromPdf(s13FormPdf())]);

    expect(S13TemplateStructure::differences(S13TemplateStructure::fromPdf(s13FormPdf())))->toBe([]);
});

test('the report of a changed template is a pre-filled issue', function () {
    $url = S13TemplateStructure::issueUrl(S13TemplateStructure::fromPdf(s13FormPdf()), ['Missing fields: Year'], 'S-13_I.pdf');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith(S13TemplateStructure::ISSUES_URL.'?')
        ->and($query['title'])->toBe('S-13 template changed')
        ->and($query['body'])->toContain('- Missing fields: Year', 'S-13_I.pdf', '"Row1.Name"');
});

test('a template with a different structure is uploaded only once confirmed', function () {
    config(['s13.expected_structure' => [...S13TemplateStructure::fromPdf(s13FormPdf()), 'fields' => ['Terr', 'Year']]]);
    actingAsS13SuperAdmin();

    $data = [
        'service_year' => TerritoryAssignmentRecord::serviceYearOf(),
        'template' => UploadedFile::fake()->createWithContent('S-13_I.pdf', s13FormPdf()),
    ];

    Livewire::test(ListTerritories::class)
        ->mountAction(DownloadTerritoryAssignmentRecordAction::getDefaultName())
        ->fillForm($data)
        ->assertSchemaComponentVisible('uploaded_structure_warning', 'mountedActionSchema0')
        ->assertSchemaComponentHidden('stored_structure_warning', 'mountedActionSchema0')
        ->assertFormFieldVisible('accept_structure_changes', 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasFormErrors(['accept_structure_changes' => 'accepted']);

    expect(TerritoryAssignmentRecordPdf::hasTemplate())->toBeFalse();

    Livewire::test(ListTerritories::class)
        ->callAction(DownloadTerritoryAssignmentRecordAction::getDefaultName(), [...$data, 'accept_structure_changes' => true])
        ->assertHasNoFormErrors();

    expect(TerritoryAssignmentRecordPdf::hasTemplate())->toBeTrue();

    Livewire::test(ListTerritories::class)
        ->mountAction(DownloadTerritoryAssignmentRecordAction::getDefaultName())
        ->assertSchemaComponentVisible('stored_structure_warning', 'mountedActionSchema0');
});

test('a template with the expected structure is uploaded without warnings', function () {
    config(['s13.expected_structure' => S13TemplateStructure::fromPdf(s13FormPdf())]);
    actingAsS13SuperAdmin();

    Livewire::test(ListTerritories::class)
        ->mountAction(DownloadTerritoryAssignmentRecordAction::getDefaultName())
        ->fillForm(['template' => UploadedFile::fake()->createWithContent('S-13_I.pdf', s13FormPdf())])
        ->assertSchemaComponentHidden('uploaded_structure_warning', 'mountedActionSchema0')
        ->assertFormFieldHidden('accept_structure_changes', 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(TerritoryAssignmentRecordPdf::hasTemplate())->toBeTrue();
});

test('the expected structure is the one of the official form', function () {
    $expected = S13TemplateStructure::expected();

    // The table of the S-13 1/22: 20 rows of two lines under a header of two lines, and four columns of assignments
    expect($expected)->not->toBeNull()
        ->and($expected['pages'])->toBe(1)
        ->and([$expected['width'], $expected['height']])->toBe([210.0, 297.0])
        ->and($expected['grid']['rows'])->toHaveCount(42)
        ->and($expected['grid']['columns'])->toHaveCount(8);
});
