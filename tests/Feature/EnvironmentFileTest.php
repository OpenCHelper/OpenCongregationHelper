<?php

use App\Support\EnvironmentFile;

beforeEach(function () {
    $this->path = tempnam(sys_get_temp_dir(), 'env');
});

afterEach(function () {
    @unlink($this->path);
});

test('only the missing values are written', function () {
    file_put_contents($this->path, "OCH_TEST_SET=kept\nOCH_TEST_EMPTY=\nOCH_TEST_QUOTED=\"\" # comment\n");

    $written = (new EnvironmentFile($this->path))->writeMissing([
        'OCH_TEST_SET' => 'changed',
        'OCH_TEST_EMPTY' => 'filled',
        'OCH_TEST_QUOTED' => 'with space',
        'OCH_TEST_NEW' => 'p@ss "word" $HOME #1',
        'OCH_TEST_BLANK' => '',
    ]);

    expect($written)->toBe(['OCH_TEST_EMPTY', 'OCH_TEST_QUOTED', 'OCH_TEST_NEW'])
        ->and(file_get_contents($this->path))->toBe(
            "OCH_TEST_SET=kept\nOCH_TEST_EMPTY=filled\nOCH_TEST_QUOTED=\"with space\"\nOCH_TEST_NEW=\"p@ss \\\"word\\\" \\\$HOME #1\"\n",
        );
});

test('the values written are read back as they were typed', function () {
    file_put_contents($this->path, "APP_NAME=Test\n");
    $file = new EnvironmentFile($this->path);

    $file->writeMissing(['OCH_TEST_PASSWORD' => 'p@ss "word" $HOME #1 \\ end']);

    expect($file->fileValues()['OCH_TEST_PASSWORD'])->toBe('p@ss "word" $HOME #1 \\ end')
        ->and(Dotenv\Dotenv::parse((string) file_get_contents($this->path))['OCH_TEST_PASSWORD'])->toBe('p@ss "word" $HOME #1 \\ end');
});

test('the variables set in the environment of the server are not missing', function () {
    file_put_contents($this->path, "OCH_TEST_EMPTY=\n");
    putenv('OCH_TEST_SERVER=value');

    $file = new EnvironmentFile($this->path);

    expect($file->missing(['OCH_TEST_EMPTY', 'OCH_TEST_SERVER', 'OCH_TEST_ABSENT']))->toBe(['OCH_TEST_EMPTY', 'OCH_TEST_ABSENT']);

    putenv('OCH_TEST_SERVER');
});

test('a file that cannot be written is reported', function () {
    file_put_contents($this->path, '');
    chmod($this->path, 0444);

    expect(fn () => (new EnvironmentFile($this->path))->writeMissing(['OCH_TEST_NEW' => 'value']))
        ->toThrow(RuntimeException::class);
})->skip(fn (): bool => function_exists('posix_getuid') && posix_getuid() === 0, 'The root user can write any file.');

test('a value can be changed in place, or added', function () {
    file_put_contents($this->path, "OCH_TEST_MAILER=log # comment\nOCH_TEST_OTHER=kept\n");
    $file = new EnvironmentFile($this->path);

    $file->set('OCH_TEST_MAILER', 'smtp');
    $file->set('OCH_TEST_NEW', 'with space');

    expect(file_get_contents($this->path))->toBe("OCH_TEST_MAILER=smtp\nOCH_TEST_OTHER=kept\nOCH_TEST_NEW=\"with space\"\n");
});

test('the variables set by the server, and not by the file, are told apart', function () {
    file_put_contents($this->path, "OCH_TEST_IN_FILE=value\n");
    putenv('OCH_TEST_IN_FILE=value');
    putenv('OCH_TEST_SERVER=value');

    $file = new EnvironmentFile($this->path);

    expect($file->isSetOutsideFile('OCH_TEST_IN_FILE'))->toBeFalse()
        ->and($file->isSetOutsideFile('OCH_TEST_SERVER'))->toBeTrue()
        ->and($file->isSetOutsideFile('OCH_TEST_ABSENT'))->toBeFalse();

    putenv('OCH_TEST_IN_FILE');
    putenv('OCH_TEST_SERVER');
});
