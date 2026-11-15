<?php

use Dotenv\Dotenv;

test('the example environment file is safe to copy to a server', function () {
    // Parsed like Laravel does, without the inline comments
    $variables = Dotenv::parse((string) file_get_contents(__DIR__.'/../../.env.example'));

    expect($variables)->toMatchArray([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'LOG_LEVEL' => 'warning',
    ]);
});
