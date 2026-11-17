<?php

use Illuminate\Filesystem\Filesystem;

require_once __DIR__.'/../../.github/actions/ftp-archive-deploy/server/SpaceDeployer.php';

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/space-deployer-'.bin2hex(random_bytes(6));
    $this->previews = $this->root.'/previews';
    $this->tokens = $this->root.'/_deployer/tokens';

    mkdir($this->previews, 0755, true);
    mkdir($this->tokens, 0755, true);

    $this->deployer = new SpaceDeployer($this->root, $this->tokens);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

/**
 * Create the uploaded archive of a release, with the given files (path => contents).
 *
 * @param  array<string, string>  $files
 */
function createReleaseArchive(string $archivePath, array $files): void
{
    $archive = new ZipArchive;
    $archive->open($archivePath, ZipArchive::CREATE);

    foreach ($files as $path => $contents) {
        $archive->addFromString($path, $contents);
    }

    $archive->close();
}

/**
 * Create files (path => contents) under the given folder.
 *
 * @param  array<string, string>  $files
 */
function createFiles(string $folder, array $files): void
{
    foreach ($files as $path => $contents) {
        if (! is_dir(dirname("{$folder}/{$path}"))) {
            mkdir(dirname("{$folder}/{$path}"), 0755, true);
        }

        file_put_contents("{$folder}/{$path}", $contents);
    }
}

/**
 * Create a live preview whose `.env` file points to the given SQLite database.
 */
function createPreviewWithDatabase(string $previewsPath, string $slug, string $databasePath, string $prefix): void
{
    createFiles("{$previewsPath}/{$slug}", ['.env' => implode("\n", [
        "APP_NAME='OpenCongregationHelper (#1)'",
        'DB_CONNECTION=sqlite',
        "DB_DATABASE='{$databasePath}'",
        "DB_PREFIX='{$prefix}'",
    ])]);
}

/**
 * @return list<string>
 */
function sqliteTables(string $databasePath): array
{
    $pdo = new PDO('sqlite:'.$databasePath);

    return array_values($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Relative paths and contents of the files under the given folder.
 *
 * @return array<string, string>
 */
function treeOf(string $folder): array
{
    $files = [];

    foreach ((new Filesystem)->allFiles($folder, hidden: true) as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    ksort($files);

    return $files;
}

test('requests are authorized only with the token uploaded under the given id', function (?string $tokenId, ?string $token, bool $authorized) {
    file_put_contents($this->tokens.'/'.str_repeat('a', 32), "secret-token\n");
    file_put_contents($this->tokens.'/'.str_repeat('b', 32), "\n");

    expect($this->deployer->isAuthorized($tokenId, $token))->toBe($authorized);
})->with([
    'valid token' => [str_repeat('a', 32), 'secret-token', true],
    'wrong token' => [str_repeat('a', 32), 'wrong', false],
    'empty token' => [str_repeat('a', 32), '', false],
    'missing token' => [str_repeat('a', 32), null, false],
    'blank token file' => [str_repeat('b', 32), '', false],
    'unknown id' => [str_repeat('c', 32), 'secret-token', false],
    'missing id' => [null, 'secret-token', false],
    'path traversal id' => ['../'.str_repeat('a', 32), 'secret-token', false],
]);

test('targets must be a preview or the folder of an environment', function (string $target) {
    $this->deployer->handle(['action' => 'activate', 'target' => $target]);
})->with(['../pr-1', 'pr-1/../..', 'pr-0', 'pr-12345', 'pr-abc', 'previews', '_deployer', '../test', 'Test', 'test/x', ''])
    ->throws(InvalidArgumentException::class);

test('unknown actions are refused', function () {
    $this->deployer->handle(['action' => 'format-disk']);
})->throws(InvalidArgumentException::class);

test('the time budget is a third of the execution time limit of the server, up to 15 seconds', function (int $maxExecutionTime, float $budget) {
    expect(SpaceDeployer::timeBudgetFor($maxExecutionTime))->toEqualWithDelta($budget, 0.001);
})->with([
    'no limit' => [0, 15.0],
    'default limit' => [30, 10.0],
    'long limit' => [300, 15.0],
    'short limit' => [2, 0.667],
]);

test('archives are unpacked in batches resumed from the returned entry', function () {
    $files = [];

    foreach (range(1, 250) as $i) {
        $files["app/file-{$i}.php"] = "<?php // {$i}";
    }

    createReleaseArchive($this->previews.'/pr-7.zip', $files + ['public/index.php' => '<?php echo "ok";']);

    $deployer = new SpaceDeployer($this->root, $this->tokens, timeBudgetSeconds: 0.0);
    $calls = 0;
    $result = ['next' => 0, 'done' => false];

    while (! $result['done']) {
        $result = $deployer->extract('pr-7', $result['next']);
        $calls++;
    }

    expect($calls)->toBe(11)
        ->and($result)->toBe(['done' => true, 'next' => 251, 'total' => 251])
        ->and(file_get_contents($this->previews.'/pr-7.next/app/file-250.php'))->toBe('<?php // 250')
        ->and(file_get_contents($this->previews.'/pr-7.next/public/index.php'))->toBe('<?php echo "ok";');
});

test('repeating the unpacking of the same entries gives the same release', function () {
    $files = [];

    foreach (range(1, 60) as $i) {
        $files["vendor/package/file-{$i}.php"] = "<?php // {$i}";
    }

    createReleaseArchive($this->root.'/test.zip', $files);

    $deployer = new SpaceDeployer($this->root, $this->tokens, timeBudgetSeconds: 0.0);
    $result = ['next' => 0, 'done' => false];

    while (! $result['done']) {
        $offset = $result['next'];
        $result = $deployer->extract('test', $offset);

        // Like a retry after a lost response (the first chunk cannot be repeated: the release folder exists by then)
        if ($offset > 0) {
            expect($deployer->extract('test', $offset))->toBe($result);
        }
    }

    ksort($files);

    expect(treeOf($this->root.'/test.next'))->toBe($files);
});

test('archives are not unpacked over a previous unpacked release', function () {
    createReleaseArchive($this->previews.'/pr-7.zip', ['public/index.php' => 'new']);
    mkdir($this->previews.'/pr-7.next');

    $this->deployer->extract('pr-7');
})->throws(RuntimeException::class, 'already exists');

test('archive entries escaping the release folder are refused', function () {
    createReleaseArchive($this->previews.'/pr-7.zip', ['../../escaped.php' => '<?php']);

    $this->deployer->extract('pr-7');
})->throws(RuntimeException::class, 'Unsafe archive entry');

test('activating a release replaces the live preview and deletes the archive', function () {
    createReleaseArchive($this->previews.'/pr-7.zip', ['public/index.php' => 'new']);
    createFiles($this->previews.'/pr-7', ['public/index.php' => 'old']);

    $this->deployer->extract('pr-7');

    expect($this->deployer->activate('pr-7'))->toBe(['activated' => true, 'replaced' => true, 'kept' => [], 'reused' => []])
        ->and(file_get_contents($this->previews.'/pr-7/public/index.php'))->toBe('new')
        ->and(file_get_contents($this->previews.'/pr-7.old/public/index.php'))->toBe('old')
        ->and(file_exists($this->previews.'/pr-7.zip'))->toBeFalse()
        ->and(file_exists($this->previews.'/pr-7.next'))->toBeFalse();
});

test('environments are deployed to their folder in the root of the space', function () {
    createReleaseArchive($this->root.'/test.zip', ['public/index.php' => 'new']);

    $this->deployer->extract('test');

    expect($this->deployer->activate('test'))->toBe(['activated' => true, 'replaced' => false, 'kept' => [], 'reused' => []])
        ->and(file_get_contents($this->root.'/test/public/index.php'))->toBe('new')
        ->and(file_exists($this->root.'/test.zip'))->toBeFalse();
});

test('activating a release keeps the given paths of the live one', function () {
    createFiles($this->root.'/test', [
        '.env' => 'APP_KEY=live',
        'storage/app/uploads/map.png' => 'uploaded',
        'storage/logs/laravel.log' => 'old logs',
        'public/index.php' => 'old',
    ]);
    createFiles($this->root.'/test.next', [
        'storage/app/.gitignore' => '*',
        'storage/logs/.gitignore' => '*',
        'public/index.php' => 'new',
    ]);

    $result = $this->deployer->activate('test', ['.env', 'storage/app', 'storage/logs', 'storage/missing']);

    expect($result)->toBe(['activated' => true, 'replaced' => true, 'kept' => ['.env', 'storage/app', 'storage/logs'], 'reused' => []])
        ->and(treeOf($this->root.'/test'))->toBe([
            '.env' => 'APP_KEY=live',
            'public/index.php' => 'new',
            'storage/app/uploads/map.png' => 'uploaded',
            'storage/logs/laravel.log' => 'old logs',
        ])
        ->and(treeOf($this->root.'/test.old'))->toBe(['public/index.php' => 'old']);
});

test('activating a release resumes after a request stopped halfway', function () {
    // The previous request moved the kept `.env` and the live release, then stopped before the last rename
    createFiles($this->root.'/test.next', ['.env' => 'APP_KEY=live', 'public/index.php' => 'new']);
    createFiles($this->root.'/test.old', ['public/index.php' => 'old']);

    expect($this->deployer->activate('test', ['.env']))->toBe(['activated' => true, 'replaced' => false, 'kept' => [], 'reused' => []])
        ->and(treeOf($this->root.'/test'))->toBe(['.env' => 'APP_KEY=live', 'public/index.php' => 'new'])
        ->and($this->deployer->activate('test', ['.env']))->toBe(['activated' => false, 'replaced' => false, 'kept' => [], 'reused' => []]);
});

test('activating a release reuses the entries of the live folders that the new release lacks', function () {
    createFiles($this->root.'/test', [
        'public/index.php' => 'old',
        'vendor/autoload.php' => 'old autoloader',
        'vendor/bin/pest' => 'proxy',
        'vendor/composer/autoload_classmap.php' => 'old classmap',
        'vendor/laravel/framework/src/Application.php' => 'framework',
    ]);
    createFiles($this->root.'/test.next', [
        'public/index.php' => 'new',
        'vendor/autoload.php' => 'new autoloader',
        'vendor/composer/autoload_classmap.php' => 'new classmap',
    ]);

    expect($this->deployer->activate('test', [], ['vendor', 'missing']))
        ->toBe(['activated' => true, 'replaced' => true, 'kept' => [], 'reused' => ['vendor']])
        // The packages come from the live release, the autoloader from the new one
        ->and(treeOf($this->root.'/test'))->toBe([
            'public/index.php' => 'new',
            'vendor/autoload.php' => 'new autoloader',
            'vendor/bin/pest' => 'proxy',
            'vendor/composer/autoload_classmap.php' => 'new classmap',
            'vendor/laravel/framework/src/Application.php' => 'framework',
        ])
        ->and(treeOf($this->root.'/test.old'))->toBe([
            'public/index.php' => 'old',
            'vendor/autoload.php' => 'old autoloader',
            'vendor/composer/autoload_classmap.php' => 'old classmap',
        ]);
});

test('reusing a folder resumes after a request stopped halfway', function () {
    // The previous request moved `vendor/a` into the new release, then stopped
    createFiles($this->root.'/test', ['public/index.php' => 'old', 'vendor/b/b.php' => 'b']);
    createFiles($this->root.'/test.next', [
        'public/index.php' => 'new',
        'vendor/a/a.php' => 'a',
        'vendor/autoload.php' => 'new autoloader',
    ]);

    expect($this->deployer->activate('test', [], ['vendor'])['reused'])->toBe(['vendor'])
        ->and(treeOf($this->root.'/test'))->toBe([
            'public/index.php' => 'new',
            'vendor/a/a.php' => 'a',
            'vendor/autoload.php' => 'new autoloader',
            'vendor/b/b.php' => 'b',
        ]);
});

test('a folder to reuse that the live release lacks is skipped, e.g. on the first deployment', function () {
    createFiles($this->previews.'/pr-7.next', ['vendor/autoload.php' => 'new autoloader']);

    expect($this->deployer->activate('pr-7', [], ['vendor']))
        ->toBe(['activated' => true, 'replaced' => false, 'kept' => [], 'reused' => []])
        ->and(treeOf($this->previews.'/pr-7'))->toBe(['vendor/autoload.php' => 'new autoloader']);
});

test('the hash of a file of the live release tells whether its packages can be reused', function () {
    createFiles($this->root.'/test', ['vendor/composer/installed.json' => '{"packages": []}']);

    expect($this->deployer->handle(['action' => 'hash', 'target' => 'test', 'path' => 'vendor/composer/installed.json']))
        ->toBe(['hash' => hash('sha256', '{"packages": []}')])
        ->and($this->deployer->hash('test', 'vendor/composer/missing.json'))->toBe(['hash' => null])
        // No live release yet
        ->and($this->deployer->hash('pr-7', 'vendor/composer/installed.json'))->toBe(['hash' => null]);
});

test('the paths to reuse and to hash must stay inside the release', function (array $input) {
    $this->deployer->handle(['target' => 'test', ...$input]);
})->with([
    'reuse of a parent folder' => [['action' => 'activate', 'reuse' => ['../vendor']]],
    'reuse not a list' => [['action' => 'activate', 'reuse' => 'vendor']],
    'hash of a file outside' => [['action' => 'hash', 'path' => '../../etc/passwd']],
    'hash without a path' => [['action' => 'hash']],
])->throws(InvalidArgumentException::class);

test('the archive can leave out a folder except some of its paths', function () {
    createFiles($this->root.'/source', [
        'app/Models/User.php' => 'user',
        'vendor/autoload.php' => 'autoloader',
        'vendor/composer/installed.json' => '{}',
        'vendor/laravel/framework/src/Application.php' => 'framework',
    ]);

    exec(
        'cd '.escapeshellarg($this->root.'/source').' && bash '
            .escapeshellarg(__DIR__.'/../../.github/actions/ftp-archive-deploy/deploy.sh')
            ." --exclude 'vendor/*' --include vendor/autoload.php --include vendor/composer create-archive "
            .escapeshellarg($this->root.'/release.zip').' 2>&1',
        $output,
        $exitCode,
    );

    $archive = new ZipArchive;
    $archive->open($this->root.'/release.zip');
    $names = array_map(fn (int $index): string => (string) $archive->getNameIndex($index), range(0, $archive->numFiles - 1));
    $archive->close();

    expect($exitCode)->toBe(0)
        ->and($names)->toContain('app/Models/User.php', 'vendor/autoload.php', 'vendor/composer/installed.json')
        ->not->toContain('vendor/laravel/framework/src/Application.php');
});

/**
 * Create the release archive of a folder with the client of the `ftp-archive-deploy` action, returning its entries.
 *
 * @return list<string>
 */
function archiveEntriesOf(string $source, string $archivePath, string $options): array
{
    exec(
        'cd '.escapeshellarg($source).' && bash '
            .escapeshellarg(__DIR__.'/../../.github/actions/ftp-archive-deploy/deploy.sh')
            .' '.$options.' create-archive '.escapeshellarg($archivePath).' 2>&1',
        $output,
        $exitCode,
    );

    expect($exitCode)->toBe(0, implode("\n", $output));

    $archive = new ZipArchive;
    $archive->open($archivePath);
    $names = array_map(fn (int $index): string => (string) $archive->getNameIndex($index), range(0, $archive->numFiles - 1));
    $archive->close();

    return $names;
}

test('the archive leaves out the paths of a git-ftp ignore file, also among the paths added back', function () {
    createFiles($this->root.'/source', [
        '.git-ftp-ignore' => "# Development files\n\nAGENTS.md\r\nscripts/*\nstorage/framework/views/*.php\nvendor/*/*/tests/*\n",
        'AGENTS.md' => 'agents',
        'app/Models/User.php' => 'user',
        'docs/AGENTS.md' => 'not at the root',
        'scripts/deploy.sh' => 'script',
        'scripts/nested/run.sh' => 'script',
        'storage/framework/views/.gitignore' => '*',
        'storage/framework/views/0a1b2c3d.php' => 'compiled',
        'vendor/autoload.php' => 'autoloader',
        'vendor/composer/installed.json' => '{}',
        'vendor/composer/semver/src/Semver.php' => 'semver',
        'vendor/composer/semver/tests/SemverTest.php' => 'test',
        'vendor/laravel/framework/src/Application.php' => 'framework',
        'vendor/laravel/framework/tests/ApplicationTest.php' => 'test',
    ]);

    $full = archiveEntriesOf($this->root.'/source', $this->root.'/full.zip', '--exclude-from .git-ftp-ignore');
    // Reusing the packages of the live release: only the autoloader is added back
    $reused = archiveEntriesOf(
        $this->root.'/source',
        $this->root.'/reused.zip',
        "--exclude-from .git-ftp-ignore --exclude 'vendor/*' --include vendor/autoload.php --include vendor/composer",
    );

    expect($full)->toContain(
        'app/Models/User.php',
        'docs/AGENTS.md',
        'storage/framework/views/.gitignore',
        'vendor/composer/semver/src/Semver.php',
        'vendor/laravel/framework/src/Application.php',
    )->and($reused)->toContain('vendor/autoload.php', 'vendor/composer/installed.json', 'vendor/composer/semver/src/Semver.php');

    foreach ([
        'AGENTS.md',
        'scripts/deploy.sh',
        'scripts/nested/run.sh',
        'storage/framework/views/0a1b2c3d.php',
        'vendor/composer/semver/tests/SemverTest.php',
        'vendor/laravel/framework/tests/ApplicationTest.php',
    ] as $path) {
        expect($full)->not->toContain($path);
    }

    expect($reused)->not->toContain('vendor/composer/semver/tests/SemverTest.php')
        ->and($reused)->not->toContain('vendor/laravel/framework/src/Application.php');
});

test('a missing ignore file fails the creation of the archive', function () {
    createFiles($this->root.'/source', ['app/Models/User.php' => 'user']);

    exec(
        'cd '.escapeshellarg($this->root.'/source').' && bash '
            .escapeshellarg(__DIR__.'/../../.github/actions/ftp-archive-deploy/deploy.sh')
            .' --exclude-from .git-ftp-ignore create-archive '.escapeshellarg($this->root.'/release.zip').' 2>&1',
        $output,
        $exitCode,
    );

    expect($exitCode)->not->toBe(0)
        ->and(implode("\n", $output))->toContain('does not exist');
});

test('a release cannot be activated before being unpacked', function () {
    $this->deployer->activate('test');
})->throws(RuntimeException::class, 'unpack the archive first');

test('the paths to keep must stay inside the release', function (mixed $keep) {
    $this->deployer->handle(['action' => 'activate', 'target' => 'test', 'keep' => $keep]);
})->with([
    'parent folder' => [['../.env']],
    'absolute path' => [['/etc/passwd']],
    'whole release' => [['.']],
    'traversal' => [['storage/../../x']],
    'not a list' => ['.env'],
    'not a string' => [[['.env']]],
])->throws(InvalidArgumentException::class);

test('leftovers are deleted in batches until done', function () {
    foreach (range(1, 20) as $i) {
        createFiles($this->previews.'/pr-7.old', ["storage/dir-{$i}/file.txt" => 'x']);
    }

    $deployer = new SpaceDeployer($this->root, $this->tokens, timeBudgetSeconds: 0.0);
    $calls = 0;

    do {
        $result = $deployer->delete('pr-7', 'old');
        $calls++;
    } while (! $result['done']);

    expect($calls)->toBeGreaterThan(1)
        ->and(file_exists($this->previews.'/pr-7.old'))->toBeFalse();
});

test('the archive left by a cancelled upload is deleted, and the client deletes it before uploading a new one', function () {
    file_put_contents($this->previews.'/pr-7.zip', 'partial');

    $client = (string) file_get_contents(__DIR__.'/../../.github/actions/ftp-archive-deploy/deploy.sh');

    expect($this->deployer->delete('pr-7', 'zip'))->toBe(['done' => true])
        ->and(file_exists($this->previews.'/pr-7.zip'))->toBeFalse()
        // Resuming the upload of a stale archive (`put -c`) would append the new one to it
        ->and(strpos($client, 'delete "$target" zip'))->toBeInt()
        ->toBeLessThan(strpos($client, "put -c '\${archive}'"));
});

test('the live release cannot be deleted', function () {
    mkdir($this->previews.'/pr-7');

    $this->deployer->handle(['action' => 'delete', 'target' => 'pr-7', 'variant' => '']);
})->throws(InvalidArgumentException::class);

test('deactivating a preview takes it offline', function () {
    mkdir($this->previews.'/pr-7');

    expect($this->deployer->deactivate('pr-7'))->toBe(['deactivated' => true])
        ->and(is_dir($this->previews.'/pr-7'))->toBeFalse()
        ->and(is_dir($this->previews.'/pr-7.old'))->toBeTrue()
        ->and($this->deployer->deactivate('pr-7'))->toBe(['deactivated' => false]);
});

test('environments cannot be taken offline nor have their database reset by the deployer', function (string $action) {
    mkdir($this->root.'/test');

    expect(fn () => $this->deployer->handle(['action' => $action, 'target' => 'test']))
        ->toThrow(InvalidArgumentException::class)
        ->and(is_dir($this->root.'/test'))->toBeTrue();
})->with(['deactivate', 'reset-database']);

test('only the previews and their leftovers are listed', function () {
    mkdir($this->previews.'/pr-12');
    mkdir($this->previews.'/pr-3');
    mkdir($this->previews.'/pr-3.old');
    touch($this->previews.'/pr-5.zip');
    mkdir($this->previews.'/not-a-preview');
    mkdir($this->root.'/test');
    mkdir($this->root.'/test.old');
    touch($this->root.'/test.zip');

    expect($this->deployer->list())->toBe([
        'previews' => ['pr-3', 'pr-12'],
        'leftovers' => ['pr-3.old', 'pr-5.zip'],
    ]);
});

test('resetting the database drops only the tables of the preview', function () {
    $databasePath = $this->root.'/database.sqlite';
    $pdo = new PDO('sqlite:'.$databasePath);

    foreach (['p7_users', 'p7_jobs', 'p70_users', 'p8_users', 'test_users', 'users'] as $table) {
        $pdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY)");
    }
    $pdo->exec('CREATE TABLE p7_assignments (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES p7_users(id))');

    createPreviewWithDatabase($this->previews, 'pr-7', $databasePath, 'p7_');

    expect($this->deployer->resetDatabase('pr-7'))->toBe(['dropped' => ['p7_assignments', 'p7_jobs', 'p7_users']])
        ->and(sqliteTables($databasePath))->toBe(['p70_users', 'p8_users', 'test_users', 'users']);
});

test('the database is not reset when the table prefix does not belong to the preview', function (string $prefix) {
    $databasePath = $this->root.'/database.sqlite';
    (new PDO('sqlite:'.$databasePath))->exec('CREATE TABLE p8_users (id INTEGER PRIMARY KEY)');

    createPreviewWithDatabase($this->previews, 'pr-7', $databasePath, $prefix);

    expect(fn () => $this->deployer->resetDatabase('pr-7'))->toThrow(RuntimeException::class, 'must be')
        ->and(sqliteTables($databasePath))->toBe(['p8_users']);
})->with(['', 'p8_', 'p', 'p7']);

/**
 * Version of the deployer bundled with a copy of the `ftp-archive-deploy` action, as computed by its client.
 */
function deployerVersion(string $actionPath): string
{
    return trim((string) shell_exec('bash '.escapeshellarg($actionPath.'/deploy.sh').' version'));
}

test('the deployer is installed in a folder named after a hash of its files', function () {
    $action = $this->root.'/ftp-archive-deploy';
    (new Filesystem)->copyDirectory(__DIR__.'/../../.github/actions/ftp-archive-deploy', $action);

    $version = deployerVersion($action);
    file_put_contents($action.'/server/SpaceDeployer.php', "\n// changed", FILE_APPEND);

    expect($version)->toMatch('/^[0-9a-f]{12}$/')
        ->and(deployerVersion($action))->toMatch('/^[0-9a-f]{12}$/')->not->toBe($version);
});

/**
 * Render the router of a space with the client of the `ftp-archive-deploy` action, returning the exit code.
 *
 * @param  list<string>  $options
 * @param  array<string, string>  $environment  Environment variables of the client (e.g. of GitHub Actions)
 */
function renderRouter(string $folder, array $options, array $environment = []): int
{
    $variables = collect($environment)
        ->map(fn (string $value, string $name): string => $name.'='.escapeshellarg($value).' ')
        ->implode('');

    exec(
        $variables.'bash '.escapeshellarg(__DIR__.'/../../.github/actions/ftp-archive-deploy/deploy.sh').' '
            .implode(' ', array_map('escapeshellarg', [...$options, 'render-router', $folder])).' 2>&1',
        $output,
        $exitCode,
    );

    return $exitCode;
}

/**
 * Request the rendered page of the missing previews on the given host, in a separate PHP process.
 *
 * @return array{status: int, body: string}
 */
function requestMissingPreviewPage(string $page, string $host): array
{
    $script = '$_SERVER["HTTP_HOST"] = '.var_export($host, true).'; ob_start(); require '.var_export($page, true).';'
        .' echo json_encode(["status" => http_response_code(), "body" => ob_get_clean()]);';

    exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($script).' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0, implode("\n", $output));

    return json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
}

test('the router serves the document root of every environment of the space on its own subdomain', function () {
    $exitCode = renderRouter($this->root.'/router', [
        '--base-url', 'https://Example.altervista.org/',
        '--default-environment', 'test',
        '--php-handler', 'av-php82',
    ]);

    $router = file_get_contents($this->root.'/router/.htaccess');

    expect($exitCode)->toBe(0)
        ->and($router)->toContain('AddHandler av-php82 .php')
        // The deployer, in the folder of its version only
        ->toContain('RewriteRule ^_deployer/[0-9a-f]{12}/(index\\.php)?$ - [L]')
        ->toContain('RewriteCond %{HTTP_HOST} ^pr-[0-9]+\\.example\\.altervista\\.org(:[0-9]+)?$ [NC]')
        ->toContain('RewriteCond %{HTTP_HOST} ^([a-z0-9-]+)\\.example\\.altervista\\.org(:[0-9]+)?$ [NC]')
        // Straight to the document root (`public/` by default): `/app` must not reach the `app/` folder of the source
        ->toContain('RewriteRule ^(.*)$ %1/public/$1 [L]')
        ->toContain('RewriteRule ^(.*)$ test/public/$1 [L]')
        ->not->toContain('{{')
        ->and(file_get_contents($this->root.'/router/previews/.htaccess'))
        ->toContain('RewriteRule ^pr-[0-9]+/public/ - [L]')
        ->toContain('RewriteRule ^(.*)$ %1/public/$1 [L]')
        // A preview without document root (e.g. of a closed PR) gets the page of the missing previews
        ->toContain('RewriteCond %2%1/public/ !-d')
        ->toContain('RewriteRule ^(pr-[0-9]+)/public/ 404.php [E=MISSING_PREVIEW:1,L]')
        // Served as is also to the subrequest of PHP running as a CGI program, finding the script it runs
        ->toContain("RewriteCond %{IS_SUBREQ} =true\n    RewriteRule ^404\\.php$ - [L]")
        ->not->toContain('{{')
        ->and(file_get_contents($this->root.'/router/previews/404.php'))
        ->not->toContain('{{');
});

test('the router keeps the previews out of the search engines with the directives allowed by shared hostings', function () {
    expect(renderRouter($this->root.'/router', ['--base-url', 'https://example.altervista.org']))->toBe(0);

    // Altervista fails every request with an error 500 on a `Header` directive in a `.htaccess` file
    expect(file_get_contents($this->root.'/router/previews/.htaccess'))
        ->not->toMatch('/^\s*Header\b/m')
        ->toContain('RewriteRule ^robots\\.txt$ - [L]')
        ->and(file_get_contents($this->root.'/router/.htaccess'))
        ->not->toMatch('/^\s*Header\b/m')
        ->and(file_get_contents($this->root.'/router/previews/robots.txt'))
        ->toContain("User-agent: *\nDisallow: /\n");
});

test('the router can serve the folders of the environments themselves', function () {
    expect(renderRouter($this->root.'/router', [
        '--base-url', 'https://example.org',
        '--default-environment', 'test',
        '--document-root', '.',
    ]))->toBe(0);

    expect(file_get_contents($this->root.'/router/.htaccess'))
        ->toContain('RewriteRule ^(.*)$ %1/$1 [L]')
        ->toContain('RewriteRule ^(.*)$ test/$1 [L]')
        ->and(file_get_contents($this->root.'/router/previews/.htaccess'))
        ->toContain('RewriteRule ^(.*)$ %1/$1 [L]')
        ->toContain('RewriteCond %2%1/ !-d');
});

test('a missing preview gets a 404 page linking to its PR', function () {
    expect(renderRouter($this->root.'/router', [
        '--base-url', 'https://example.altervista.org',
        '--repository-url', 'https://github.com/acme/app',
    ]))->toBe(0);

    $response = requestMissingPreviewPage($this->root.'/router/previews/404.php', 'pr-7.example.altervista.org');

    expect($response['status'])->toBe(404)
        ->and($response['body'])
        ->toContain('No preview for PR #7')
        ->toContain('href="https://github.com/acme/app/pull/7"');
});

test('the page of the missing previews links to the PRs of the repository running the deployment', function (array $options, array $environment, ?string $link) {
    expect(renderRouter($this->root.'/router', ['--base-url', 'http://example.org:8080', ...$options], $environment))
        ->toBe(0);

    $body = requestMissingPreviewPage($this->root.'/router/previews/404.php', 'pr-7.example.org:8080')['body'];

    expect($body)->toContain('No preview for PR #7');

    if ($link === null) {
        expect($body)->not->toContain('/pull/');
    } else {
        expect($body)->toContain('href="'.$link.'"');
    }
})->with([
    'GitHub Actions' => [[], ['GITHUB_SERVER_URL' => 'https://github.com', 'GITHUB_REPOSITORY' => 'acme/app'], 'https://github.com/acme/app/pull/7'],
    'given repository' => [['--repository-url', 'https://git.example.org/acme/app'], ['GITHUB_SERVER_URL' => 'https://github.com', 'GITHUB_REPOSITORY' => 'acme/app'], 'https://git.example.org/acme/app/pull/7'],
    'no repository' => [['--repository-url', ''], ['GITHUB_SERVER_URL' => 'https://github.com', 'GITHUB_REPOSITORY' => 'acme/app'], null],
]);

test('without a default environment nor a PHP handler, the router serves nothing on the site', function () {
    expect(renderRouter($this->root.'/router', ['--base-url', 'http://example.org:8080']))->toBe(0);

    expect(file_get_contents($this->root.'/router/.htaccess'))
        ->toContain('RewriteRule ^ - [R=404,L]')
        ->toContain('^([a-z0-9-]+)\\.example\\.org(:[0-9]+)?$')
        ->not->toContain('AddHandler');
});

test('the router refuses invalid settings', function (array $options) {
    expect(renderRouter($this->root.'/router', $options))->not->toBe(0)
        ->and(file_exists($this->root.'/router/.htaccess'))->toBeFalse();
})->with([
    'reserved default environment' => [['--base-url', 'https://example.org', '--default-environment', 'previews']],
    'invalid default environment' => [['--base-url', 'https://example.org', '--default-environment', '../x']],
    'invalid PHP handler' => [['--base-url', 'https://example.org', '--php-handler', 'a b']],
    'document root outside the environment' => [['--base-url', 'https://example.org', '--document-root', '../etc']],
    'invalid host' => [['--base-url', 'https://exa$mple.org']],
    'invalid repository URL' => [['--base-url', 'https://example.org', '--repository-url', "https://github.com/acme/app'"]],
]);

/**
 * Install the router of a space with the client of the `ftp-archive-deploy` action, on a fake FTP server serving the
 * given folder, returning the FTP commands it received.
 *
 * The fake `lftp` runs the `get` and `mirror` commands of the client on the folder: the router is uploaded with
 * `mirror` and its installed files are read with `get`.
 *
 * @return list<string>
 */
function installRouterOn(string $space, string $workDir): array
{
    $bin = $workDir.'/bin';
    $log = $workDir.'/ftp-commands.log';

    if (! is_dir($bin)) {
        mkdir($bin, 0755, true);

        file_put_contents($bin.'/lftp', <<<'BASH'
            #!/usr/bin/env bash
            while IFS= read -r line; do
                [[ -z "$line" ]] && continue
                printf '%s\n' "$line" >> "$FAKE_FTP_LOG"
                eval "set -- $line"
                case "$1" in
                    get) [[ -f "${FAKE_FTP_ROOT}/$2" ]] && cp "${FAKE_FTP_ROOT}/$2" "$4" ;;
                    mirror) cp -r "${@: -2:1}/." "${FAKE_FTP_ROOT}/" ;;
                esac
            done
            exit 0
            BASH);

        // Required by the client, but not used to install the router
        file_put_contents($bin.'/curl', "#!/usr/bin/env bash\nexit 1\n");
        file_put_contents($bin.'/jq', "#!/usr/bin/env bash\nexit 1\n");
        array_map(fn (string $command): bool => chmod($bin.'/'.$command, 0755), ['lftp', 'curl', 'jq']);
    }

    if (file_exists($log)) {
        unlink($log);
    }

    exec(
        'PATH='.escapeshellarg($bin).':"$PATH" FAKE_FTP_ROOT='.escapeshellarg($space).' FAKE_FTP_LOG='.escapeshellarg($log)
            .' bash '.escapeshellarg(__DIR__.'/../../.github/actions/ftp-archive-deploy/deploy.sh')
            .' --base-url https://example.altervista.org --default-environment test --php-handler av-php82'
            .' --ftp-hostname ftp.example.org --ftp-username example --ftp-password secret install-router 2>&1',
        $output,
        $exitCode,
    );

    expect($exitCode)->toBe(0, implode("\n", $output));

    return file_exists($log) ? file($log, FILE_IGNORE_NEW_LINES) : [];
}

/**
 * Whether the given FTP commands upload the router.
 *
 * @param  list<string>  $commands
 */
function uploadsRouter(array $commands): bool
{
    return collect($commands)->contains(fn (string $command): bool => str_starts_with($command, 'mirror --reverse'));
}

test('the router is uploaded when the space has none or an outdated one', function () {
    mkdir($this->root.'/space');

    expect(uploadsRouter(installRouterOn($this->root.'/space', $this->root)))->toBeTrue()
        ->and(file_get_contents($this->root.'/space/.htaccess'))->toContain('RewriteRule ^(.*)$ test/public/$1 [L]')
        ->and(file_exists($this->root.'/space/previews/.htaccess'))->toBeTrue()
        ->and(file_exists($this->root.'/space/previews/404.php'))->toBeTrue()
        ->and(file_exists($this->root.'/space/previews/robots.txt'))->toBeTrue();

    file_put_contents($this->root.'/space/previews/.htaccess', "# outdated\n");

    expect(uploadsRouter(installRouterOn($this->root.'/space', $this->root)))->toBeTrue()
        ->and(file_get_contents($this->root.'/space/previews/.htaccess'))->not->toContain('# outdated');
});

test('the router is not uploaded again when the installed one is up to date', function () {
    mkdir($this->root.'/space');
    installRouterOn($this->root.'/space', $this->root);

    // Uploading it again would truncate the `.htaccess` of the space for a moment, failing the requests of a
    // deployment running at the same time
    $commands = installRouterOn($this->root.'/space', $this->root);

    expect(uploadsRouter($commands))->toBeFalse()
        ->and(collect($commands)->map(fn (string $command): string => strtok($command, ' ').' '.strtok(' '))->all())
        ->toEqualCanonicalizing(["get '.htaccess'", "get 'previews/.htaccess'", "get 'previews/404.php'", "get 'previews/robots.txt'"]);
});
