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
        "APP_NAME='Registro Territori (#1)'",
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

    expect($this->deployer->activate('pr-7'))->toBe(['activated' => true, 'replaced' => true, 'kept' => []])
        ->and(file_get_contents($this->previews.'/pr-7/public/index.php'))->toBe('new')
        ->and(file_get_contents($this->previews.'/pr-7.old/public/index.php'))->toBe('old')
        ->and(file_exists($this->previews.'/pr-7.zip'))->toBeFalse()
        ->and(file_exists($this->previews.'/pr-7.next'))->toBeFalse();
});

test('environments are deployed to their folder in the root of the space', function () {
    createReleaseArchive($this->root.'/test.zip', ['public/index.php' => 'new']);

    $this->deployer->extract('test');

    expect($this->deployer->activate('test'))->toBe(['activated' => true, 'replaced' => false, 'kept' => []])
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

    expect($result)->toBe(['activated' => true, 'replaced' => true, 'kept' => ['.env', 'storage/app', 'storage/logs']])
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

    expect($this->deployer->activate('test', ['.env']))->toBe(['activated' => true, 'replaced' => false, 'kept' => []])
        ->and(treeOf($this->root.'/test'))->toBe(['.env' => 'APP_KEY=live', 'public/index.php' => 'new'])
        ->and($this->deployer->activate('test', ['.env']))->toBe(['activated' => false, 'replaced' => false, 'kept' => []]);
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
 */
function renderRouter(string $folder, array $options): int
{
    exec(
        'bash '.escapeshellarg(__DIR__.'/../../.github/actions/ftp-archive-deploy/deploy.sh').' '
            .implode(' ', array_map('escapeshellarg', [...$options, 'render-router', $folder])).' 2>&1',
        $output,
        $exitCode,
    );

    return $exitCode;
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
        ->toContain('RewriteRule ^(.*)$ %1/$1 [L]');
});

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
]);
