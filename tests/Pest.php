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

    foreach ($grid['rows'] as $y) {
        $pdf->Rect(20, $y - 0.1, 150, 0.2, 'F');
    }

    return $pdf->Output('S');
}
