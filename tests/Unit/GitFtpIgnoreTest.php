<?php

/**
 * Whether git-ftp leaves a path out according to the given `.git-ftp-ignore` file: like its `glob_filter`, each line
 * is a shell glob matched against the whole path, where `*` also matches `/` (`fnmatch()` without `FNM_PATHNAME`).
 */
function isNeverDeployed(string $path, string $ignoreFile): bool
{
    $patterns = array_filter(
        array_map(fn (string $line): string => rtrim($line, "\r"), file($ignoreFile, FILE_IGNORE_NEW_LINES)),
        fn (string $line): bool => trim($line) !== '' && ! str_starts_with($line, '#'),
    );

    foreach ($patterns as $pattern) {
        if (fnmatch($pattern, $path)) {
            return true;
        }
    }

    return false;
}

/**
 * Run `scripts/git_ftp_ignore_globs.sh` on a file, returning its globs.
 *
 * @return list<string>
 */
function ftpDeployGlobs(string $ignoreFile, string $scope): array
{
    exec(
        'bash '.escapeshellarg(__DIR__.'/../../scripts/git_ftp_ignore_globs.sh').' '
            .escapeshellarg($ignoreFile).' '.escapeshellarg($scope).' 2>&1',
        $output,
        $exitCode,
    );

    expect($exitCode)->toBe(0);

    return $output;
}

test('the files never deployed include what serves only to develop, build or test the application', function (string $path) {
    expect(isNeverDeployed($path, __DIR__.'/../../.git-ftp-ignore'))->toBeTrue();
})->with([
    '.github/workflows/deploy-preview.yml',
    '.devcontainer/devcontainer.json',
    'tests/Unit/GitFtpIgnoreTest.php',
    'AGENTS.md',
    'README.md',
    'composer.lock',
    'package.json',
    'vite.config.js',
    'resources/css/app.css',
    'resources/js/app.js',
    'scripts/remote_artisan.sh',
    '_ide_helper_models.php',
    'storage/framework/views/0a1b2c3d.php',
    'public/storage/.gitignore',
    'vendor/laravel/framework/tests/Foundation/FoundationApplicationTest.php',
    'vendor/filament/filament/docs/01-introduction.md',
    'vendor/composer/semver/.github/workflows/ci.yml',
]);

test('the files needed on the server are always deployed', function (string $path) {
    expect(isNeverDeployed($path, __DIR__.'/../../.git-ftp-ignore'))->toBeFalse();
})->with([
    'CHANGELOG.md',
    'composer.json',
    'artisan',
    '.htaccess',
    '.git-commit-hash',
    '.git-branch-name',
    'app/Models/User.php',
    'bootstrap/cache/packages.php',
    'config/app.php',
    'database/migrations/0001_01_01_000000_create_users_table.php',
    'database/factories/UserFactory.php',
    'docs/user-documentation/en/setup.md',
    'lang/it.json',
    'public/index.php',
    'public/build/manifest.json',
    'resources/views/welcome.blade.php',
    'resources/markdown/policy.md',
    'routes/web.php',
    'storage/framework/views/.gitignore',
    'storage/app/public/.gitignore',
    'vendor/autoload.php',
    'vendor/composer/installed.json',
    'vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
]);

test('the files never deployed are converted to the globs of the FTP-Deploy action', function () {
    $ignoreFile = sys_get_temp_dir().'/git-ftp-ignore-'.bin2hex(random_bytes(6));
    file_put_contents($ignoreFile, implode("\n", [
        '# Comment',
        '',
        "AGENTS.md\r",
        'scripts/*',
        'storage/framework/views/*.php',
        'vendor/*/*/tests/*',
    ])."\n");

    try {
        expect(ftpDeployGlobs($ignoreFile, 'app'))->toBe(['AGENTS.md', 'scripts/**', 'storage/framework/views/*.php'])
            // Relative to `vendor/`, which is deployed on its own
            ->and(ftpDeployGlobs($ignoreFile, 'vendor'))->toBe(['**/**/tests/**']);
    } finally {
        unlink($ignoreFile);
    }
});
