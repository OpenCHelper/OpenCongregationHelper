<?php

use App\Providers\Filament\AppPanelProvider;
use Filament\Support\Colors\Color;

test('each known environment has its own indicator color', function (string $environment, array $color) {
    expect(AppPanelProvider::environmentColor($environment))->toBe($color);
})->with([
    'local' => ['local', Color::Blue],
    'nightly' => ['nightly', Color::Amber],
    'preview' => ['preview', Color::Purple],
    'demo' => ['demo', Color::Purple],
    'staging' => ['staging', Color::Orange],
    'uat' => ['uat', Color::Orange],
    'production' => ['production', Color::Red],
]);

test('unknown environments use the fallback indicator color', function () {
    expect(AppPanelProvider::environmentColor('something-else'))->toBe(Color::Fuchsia);
});
