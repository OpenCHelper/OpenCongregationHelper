<?php

/**
 * Load the map configuration with the given `MAP_DEFAULT_CENTER` (unset when null).
 *
 * @return array{float, float}
 */
function mapDefaultCenter(?string $value): array
{
    $previous = $_SERVER['MAP_DEFAULT_CENTER'] ?? null;

    if ($value === null) {
        unset($_SERVER['MAP_DEFAULT_CENTER'], $_ENV['MAP_DEFAULT_CENTER']);
    } else {
        $_SERVER['MAP_DEFAULT_CENTER'] = $_ENV['MAP_DEFAULT_CENTER'] = $value;
    }

    try {
        return (require config_path('filament-leaflet.php'))['default_map_center'];
    } finally {
        unset($_SERVER['MAP_DEFAULT_CENTER'], $_ENV['MAP_DEFAULT_CENTER']);

        if ($previous !== null) {
            $_SERVER['MAP_DEFAULT_CENTER'] = $_ENV['MAP_DEFAULT_CENTER'] = $previous;
        }
    }
}

test('the maps open on Rome when no default centre is configured', function () {
    expect(mapDefaultCenter(null))->toBe([41.896961975616, 12.481241781767]);
});

test('the default map centre is read from comma separated coordinates', function (string $value) {
    expect(mapDefaultCenter($value))->toBe([39.8017, -89.6436]);
})->with([
    'without spaces' => '39.8017,-89.6436',
    'with spaces' => ' 39.8017 , -89.6436 ',
]);
