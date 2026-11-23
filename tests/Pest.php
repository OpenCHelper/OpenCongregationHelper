<?php

use App\Support\S13Layout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Whether Vite can serve the frontend assets: built with `npm run build`
 * (as in CI) or served by the development server.
 */
function frontendAssetsAreAvailable(): bool
{
    return Vite::isRunningHot() || Vite::manifestHash() !== null;
}

/**
 * An A4 PDF with only the lines of the table of the official S-13 form (see `S13Layout::EXPECTED_STRUCTURE`), standing
 * in for the form, which is not shipped with the application.
 */
function blankS13Template(): string
{
    $pdf = new Fpdi;
    $pdf->AddPage();
    $grid = S13Layout::EXPECTED_STRUCTURE['grid'];

    foreach ($grid['columns'] as $x) {
        $pdf->Rect($x - 0.1, 50, 0.2, 200, 'F');
    }

    foreach ($grid['rows'] as $index => $y) {
        // The topmost line is the one of the service year, as on the form
        $index === 0 ? $pdf->Rect(49.96, $y - 0.17, 22.78, 0.34, 'F') : $pdf->Rect(20, $y - 0.1, 150, 0.2, 'F');
    }

    return $pdf->Output('S');
}

/**
 * The same template as `blankS13Template()`, saved as recent PDF writers do (e.g. the Adobe PDF Library for the
 * S-13-E): the objects in a compressed object stream and the cross-reference in a compressed stream, which the free
 * parser of FPDI cannot read.
 */
function compressedXrefS13Template(): string
{
    $content = (string) preg_replace('/^.*?stream\r?\n(.*?)\r?\nendstream.*$/s', '$1', (string) gzuncompress(
        (string) preg_replace('/^.*?\/Length \d+>>\nstream\n(.*?)\nendstream.*$/s', '$1', blankS13Template()),
    ));

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents 4 0 R >>',
    ];
    $header = '';
    $body = '';

    foreach ($objects as $number => $object) {
        $header .= $number.' '.strlen($body).' ';
        $body .= $object."\n";
    }

    $objectStream = gzcompress($header.$body);
    $contentStream = gzcompress($content);
    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $offsets[4] = strlen($pdf);
    $pdf .= "4 0 obj\n<< /Filter /FlateDecode /Length ".strlen($contentStream)." >>\nstream\n{$contentStream}\nendstream\nendobj\n";
    $offsets[5] = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Type /ObjStm /N 3 /First ".strlen($header).' /Filter /FlateDecode /Length '.strlen($objectStream)." >>\nstream\n{$objectStream}\nendstream\nendobj\n";
    $offsets[6] = strlen($pdf);

    // Entries of 1 + 4 + 2 bytes: type (0 free, 1 at an offset, 2 in an object stream), offset or stream, generation or index
    $entry = fn (int $type, int $field, int $index): string => pack('CNn', $type, $field, $index);
    $entries = $entry(0, 0, 65535).$entry(2, 5, 0).$entry(2, 5, 1).$entry(2, 5, 2)
        .$entry(1, $offsets[4], 0).$entry(1, $offsets[5], 0).$entry(1, $offsets[6], 0);
    $xrefStream = gzcompress($entries);

    return $pdf."6 0 obj\n<< /Type /XRef /Size 7 /W [1 4 2] /Root 1 0 R /Filter /FlateDecode /Length ".strlen($xrefStream)
        ." >>\nstream\n{$xrefStream}\nendstream\nendobj\nstartxref\n{$offsets[6]}\n%%EOF\n";
}
