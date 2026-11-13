<?php

declare(strict_types=1);

/**
 * Server side of the deployments to an Altervista space hosting several environments (see the README): the PR
 * previews (`.github/workflows/preview.yml`) and the environments living in a folder of their own, like TEST.
 *
 * Altervista only offers FTP, which takes hours to upload the ~20k files of a release one by one: the release is
 * uploaded as a single archive instead, and this class unpacks it on the server and then swaps the folders. No request
 * runs longer than a time budget, which stays well within the PHP execution time limit: the long tasks (unpacking an
 * archive, deleting a release) are split in batches, each request saying where the next one has to resume, and every
 * action can be safely repeated when a request fails.
 *
 * It must not depend on Laravel nor on Composer: it runs before the `vendor` folder of a release exists.
 *
 * Targets, and their folders in the space (each with its `.zip` archive, `.next` release being unpacked and `.old`
 * release being deleted next to it):
 *
 *  - `pr-<N>`: the preview of PR #N, in `previews/pr-<N>`;
 *  - `<name>` (e.g. `test`): an environment, in `<name>`.
 */
final class SpaceDeployer
{
    /**
     * Folder name of a preview: PR numbers have at most 4 digits, so that the table prefix (`p<N>_`, at most 6
     * characters) keeps the longest generated index name within the 64 characters allowed by MySQL.
     */
    public const PREVIEW_PATTERN = '/^pr-[1-9][0-9]{0,3}$/';

    /**
     * Folder name of an environment (served on `<name>.<site>` by the router of the space).
     */
    public const ENVIRONMENT_PATTERN = '/^[a-z][a-z0-9-]{0,30}$/';

    public const TOKEN_ID_PATTERN = '/^[A-Za-z0-9]{32,64}$/';

    /**
     * Longest time budget of a request, in seconds.
     */
    public const MAX_TIME_BUDGET = 15.0;

    /**
     * Shortest time budget a request can ask for, in seconds.
     */
    private const MIN_TIME_BUDGET = 0.25;

    /**
     * Folders of the space that are not environments.
     */
    private const RESERVED_FOLDERS = ['previews'];

    /**
     * Number of archive entries unpacked between two checks of the time budget: the most a request can overrun it.
     */
    private const EXTRACTION_CHUNK_SIZE = 25;

    /**
     * @param  int|null  $maxExecutionTime  The PHP execution time limit the budget was computed from (shown by `info`)
     */
    public function __construct(
        private readonly string $spacePath,
        private readonly string $tokensPath,
        private readonly float $timeBudgetSeconds = self::MAX_TIME_BUDGET,
        private readonly ?int $maxExecutionTime = null,
    ) {}

    /**
     * Time budget of the requests for the given PHP execution time limit (`max_execution_time`, 0 for none): a third
     * of it, so that a slow batch still ends well before the limit, and never more than `MAX_TIME_BUDGET`.
     */
    public static function timeBudgetFor(int $maxExecutionTime): float
    {
        return $maxExecutionTime > 0
            ? min(self::MAX_TIME_BUDGET, $maxExecutionTime / 3)
            : self::MAX_TIME_BUDGET;
    }

    /**
     * Whether the given token matches the one uploaded (through FTP) in the tokens folder under the given ID.
     */
    public function isAuthorized(?string $tokenId, ?string $token): bool
    {
        if ($tokenId === null || $token === null || $token === '' || ! preg_match(self::TOKEN_ID_PATTERN, $tokenId)) {
            return false;
        }

        $tokenFile = $this->tokensPath.'/'.$tokenId;

        if (! is_file($tokenFile)) {
            return false;
        }

        $expectedToken = trim((string) file_get_contents($tokenFile));

        return $expectedToken !== '' && hash_equals($expectedToken, $token);
    }

    /**
     * Run the action of a request.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $action = $input['action'] ?? null;

        return match ($action) {
            'info' => $this->info(),
            'list' => $this->list(),
            'extract' => $this->extract($this->target($input), (int) ($input['offset'] ?? 0), $this->budget($input)),
            'hash' => $this->hash($this->target($input), $this->safeRelativePath((string) ($input['path'] ?? ''))),
            'activate' => $this->activate(
                $this->target($input),
                $this->relativePaths($input, 'keep'),
                $this->relativePaths($input, 'reuse'),
            ),
            'deactivate' => $this->deactivate($this->preview($input)),
            'delete' => $this->delete($this->target($input), (string) ($input['variant'] ?? ''), $this->budget($input)),
            'reset-database' => $this->resetDatabase($this->preview($input)),
            default => throw new InvalidArgumentException('Unknown action.'),
        };
    }

    /**
     * Details of the server, to check that it can host the deployments.
     *
     * @return array{php_version: string, max_execution_time: int|string|false, time_budget: float, memory_limit: string|false, zip: bool, pdo_drivers: list<string>}
     */
    public function info(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'max_execution_time' => $this->maxExecutionTime ?? ini_get('max_execution_time'),
            'time_budget' => $this->timeBudgetSeconds,
            'memory_limit' => ini_get('memory_limit'),
            'zip' => class_exists(ZipArchive::class),
            'pdo_drivers' => class_exists(PDO::class) ? array_values(PDO::getAvailableDrivers()) : [],
        ];
    }

    /**
     * The live previews, and the leftovers of their interrupted deployments (environments are never listed).
     *
     * @return array{previews: list<string>, leftovers: list<string>}
     */
    public function list(): array
    {
        $previewsPath = $this->spacePath.'/previews';
        $previews = [];
        $leftovers = [];

        foreach (is_dir($previewsPath) ? scandir($previewsPath) : [] as $name) {
            if (preg_match(self::PREVIEW_PATTERN, $name) && is_dir($previewsPath.'/'.$name)) {
                $previews[] = $name;
            } elseif (preg_match('/^(pr-[0-9]+)\.(next|old|zip)$/', $name)) {
                $leftovers[] = $name;
            }
        }

        natsort($previews);
        natsort($leftovers);

        return ['previews' => array_values($previews), 'leftovers' => array_values($leftovers)];
    }

    /**
     * The SHA-256 of a file of the live release of a target (e.g. `vendor/composer/installed.json`), or `null` when the
     * target or the file does not exist: the client compares it with its own copy to know what it can reuse.
     *
     * @return array{hash: ?string}
     */
    public function hash(string $target, string $path): array
    {
        $file = $this->path($target).'/'.$this->safeRelativePath($path);

        return ['hash' => is_file($file) && ! is_link($file) ? hash_file('sha256', $file) : null];
    }

    /**
     * Unpack the uploaded archive of a target into `<target>.next`, starting from the given entry, until the time
     * budget runs out: call it again with the returned `next` entry until it is `done`.
     *
     * Repeating a call (e.g. when its response was lost) unpacks the same entries again, overwriting them.
     *
     * @return array{done: bool, next: int, total: int}
     */
    public function extract(string $target, int $offset = 0, ?float $budget = null): array
    {
        $budget ??= $this->timeBudgetSeconds;
        $archivePath = $this->path($target, 'zip');
        $releasePath = $this->path($target, 'next');

        $archive = new ZipArchive;

        if ($archive->open($archivePath) !== true) {
            throw new RuntimeException("Cannot open the archive of {$target}.");
        }

        try {
            $total = $archive->numFiles;
            $offset = max(0, $offset);

            if ($offset === 0 && file_exists($releasePath)) {
                throw new RuntimeException("{$target}.next already exists: delete it before unpacking the archive again.");
            }

            if (! is_dir($releasePath) && ! mkdir($releasePath, 0755, true)) {
                throw new RuntimeException("Cannot create {$target}.next.");
            }

            $startedAt = microtime(true);
            $next = $offset;

            // At least one chunk per call, so that every call makes progress
            while ($next < $total && ($next === $offset || microtime(true) - $startedAt < $budget)) {
                $names = [];

                for ($index = $next; $index < min($total, $next + self::EXTRACTION_CHUNK_SIZE); $index++) {
                    $names[] = $this->safeEntryName((string) $archive->getNameIndex($index));
                }

                if (! $archive->extractTo($releasePath, $names)) {
                    throw new RuntimeException("Cannot unpack the archive of {$target} (entries {$next}+).");
                }

                $next += count($names);
            }

            return ['done' => $next >= $total, 'next' => $next, 'total' => $total];
        } finally {
            $archive->close();
        }
    }

    /**
     * Replace the live release of a target with the unpacked one, moving the previous release to `<target>.old` (to be
     * deleted), and delete the archive.
     *
     * The `keep` paths (e.g. `.env` and `storage/app` for an environment whose files are managed on the server) are
     * moved from the live release into the new one first, replacing the copies of the new release.
     *
     * Then, the entries of the `reuse` folders of the live release that the new release lacks are moved into it, while
     * those it has stay: e.g. the packages of `vendor/`, when the archive carries only `vendor/autoload.php` and
     * `vendor/composer/` because the packages did not change. Moving them is instant, instead of unpacking them again.
     *
     * Every step can be repeated when a request fails halfway: the paths already moved are no longer in the live
     * release, the live release is no longer there once moved to `.old`, and the release was already activated when
     * there is no `.next` one left.
     *
     * @param  list<string>  $keep
     * @param  list<string>  $reuse
     * @return array{activated: bool, replaced: bool, kept: list<string>, reused: list<string>}
     */
    public function activate(string $target, array $keep = [], array $reuse = []): array
    {
        $livePath = $this->path($target);
        $releasePath = $this->path($target, 'next');

        if (! is_dir($releasePath)) {
            if (is_dir($livePath)) {
                // Already activated by a previous request, whose response was lost
                $this->deleteArchive($target);

                return ['activated' => false, 'replaced' => false, 'kept' => [], 'reused' => []];
            }

            throw new RuntimeException("{$target}.next does not exist: unpack the archive first.");
        }

        $kept = [];

        foreach ($keep as $path) {
            $path = $this->safeRelativePath($path);

            if (! file_exists($livePath.'/'.$path) && ! is_link($livePath.'/'.$path)) {
                continue;
            }

            $this->removeTree($releasePath.'/'.$path);

            if (! is_dir(dirname($releasePath.'/'.$path)) && ! mkdir(dirname($releasePath.'/'.$path), 0755, true)) {
                throw new RuntimeException("Cannot create the parent folder of {$path} in {$target}.next.");
            }

            if (! rename($livePath.'/'.$path, $releasePath.'/'.$path)) {
                throw new RuntimeException("Cannot move {$path} of {$target} to the new release.");
            }

            $kept[] = $path;
        }

        $reused = [];

        foreach ($reuse as $path) {
            $path = $this->safeRelativePath($path);
            $liveFolder = $livePath.'/'.$path;
            $releaseFolder = $releasePath.'/'.$path;

            if (! is_dir($liveFolder) || is_link($liveFolder)) {
                continue;
            }

            if (! is_dir($releaseFolder) && ! mkdir($releaseFolder, 0755, true)) {
                throw new RuntimeException("Cannot create {$path} in {$target}.next.");
            }

            foreach (array_diff(scandir($liveFolder) ?: [], ['.', '..']) as $name) {
                if (file_exists($releaseFolder.'/'.$name) || is_link($releaseFolder.'/'.$name)) {
                    continue;
                }

                if (! rename($liveFolder.'/'.$name, $releaseFolder.'/'.$name)) {
                    throw new RuntimeException("Cannot move {$path}/{$name} of {$target} to the new release.");
                }
            }

            $reused[] = $path;
        }

        $replaced = false;

        if (is_dir($livePath)) {
            if (file_exists($this->path($target, 'old'))) {
                throw new RuntimeException("{$target}.old still exists: delete it first.");
            }

            if (! rename($livePath, $this->path($target, 'old'))) {
                throw new RuntimeException("Cannot move {$target} to {$target}.old.");
            }

            $replaced = true;
        }

        if (! rename($releasePath, $livePath)) {
            throw new RuntimeException("Cannot move {$target}.next to {$target}.");
        }

        $this->deleteArchive($target);

        return ['activated' => true, 'replaced' => $replaced, 'kept' => $kept, 'reused' => $reused];
    }

    /**
     * Take a preview offline at once, by moving it to `<target>.old` (to be deleted).
     *
     * @return array{deactivated: bool}
     */
    public function deactivate(string $target): array
    {
        if (! $this->isPreview($target)) {
            throw new InvalidArgumentException('Only previews can be deactivated.');
        }

        if (! is_dir($this->path($target))) {
            return ['deactivated' => false];
        }

        if (file_exists($this->path($target, 'old'))) {
            throw new RuntimeException("{$target}.old still exists: delete it first.");
        }

        if (! rename($this->path($target), $this->path($target, 'old'))) {
            throw new RuntimeException("Cannot move {$target} to {$target}.old.");
        }

        return ['deactivated' => true];
    }

    /**
     * Delete the given leftover of a target (`next`, `old` or `zip`) until the time budget runs out: call it again
     * until it is `done`. The live release is never deleted: a preview must be deactivated first.
     *
     * @return array{done: bool}
     */
    public function delete(string $target, string $variant, ?float $budget = null): array
    {
        if (! in_array($variant, ['next', 'old', 'zip'], true)) {
            throw new InvalidArgumentException('The variant must be one of: next, old, zip.');
        }

        $path = $this->path($target, $variant);

        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            $this->deleteDirectory($path, microtime(true), $budget ?? $this->timeBudgetSeconds);
        }

        return ['done' => ! file_exists($path) && ! is_link($path)];
    }

    /**
     * Drop the database tables of a preview, using the connection and the table prefix of its `.env` file.
     *
     * Only the tables starting with the prefix of the preview (`p<N>_` for `pr-<N>`) are dropped. Environments reset
     * their own database with `migrate:fresh`, which only drops their prefixed tables too (`PrefixAwareWipeCommand`).
     *
     * @return array{dropped: list<string>}
     */
    public function resetDatabase(string $target): array
    {
        if (! $this->isPreview($target)) {
            throw new InvalidArgumentException('Only the database of previews can be reset.');
        }

        $environment = $this->readEnvironment($target);
        $prefix = 'p'.substr($target, strlen('pr-')).'_';

        if (($environment['DB_PREFIX'] ?? '') !== $prefix) {
            throw new RuntimeException("The table prefix of {$target} must be '{$prefix}'.");
        }

        $driver = $environment['DB_CONNECTION'] ?? '';

        $pdo = match ($driver) {
            'mysql', 'mariadb' => new PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $environment['DB_HOST'] ?? 'localhost',
                    $environment['DB_PORT'] ?? '3306',
                    $environment['DB_DATABASE'] ?? '',
                ),
                $environment['DB_USERNAME'] ?? '',
                $environment['DB_PASSWORD'] ?? '',
            ),
            'sqlite' => new PDO('sqlite:'.($environment['DB_DATABASE'] ?? '')),
            default => throw new RuntimeException("Unsupported database connection '{$driver}'."),
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tables = array_values(array_filter(
            $pdo->query($driver === 'sqlite'
                ? "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
                : "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
            )->fetchAll(PDO::FETCH_COLUMN),
            fn (string $table): bool => str_starts_with($table, $prefix),
        ));

        if ($tables === []) {
            return ['dropped' => []];
        }

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF');

            foreach ($tables as $table) {
                $pdo->exec('DROP TABLE "'.str_replace('"', '""', $table).'"');
            }
        } else {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            try {
                $pdo->exec('DROP TABLE '.implode(', ', array_map(
                    fn (string $table): string => '`'.str_replace('`', '``', $table).'`',
                    $tables,
                )));
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        }

        sort($tables);

        return ['dropped' => $tables];
    }

    /**
     * The validated `target` of a request: a preview (`pr-<N>`) or an environment (`<name>`).
     *
     * @param  array<string, mixed>  $input
     */
    private function target(array $input): string
    {
        $target = $input['target'] ?? null;

        if (is_string($target) && preg_match(self::PREVIEW_PATTERN, $target)) {
            return $target;
        }

        if (
            ! is_string($target)
            || ! preg_match(self::ENVIRONMENT_PATTERN, $target)
            || in_array($target, self::RESERVED_FOLDERS, true)
            || str_starts_with($target, 'pr-')
        ) {
            throw new InvalidArgumentException(
                'The target must be a preview (pr-<N>, where N is a PR number up to 9999) or the folder of an environment (e.g. test).'
            );
        }

        return $target;
    }

    /**
     * The validated `target` of a request, which must be a preview.
     *
     * @param  array<string, mixed>  $input
     */
    private function preview(array $input): string
    {
        $target = $this->target($input);

        if (! $this->isPreview($target)) {
            throw new InvalidArgumentException('This action is only available for previews (pr-<N>).');
        }

        return $target;
    }

    /**
     * The validated paths of the live release given in a field of the request (`keep` or `reuse`).
     *
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function relativePaths(array $input, string $field): array
    {
        $paths = $input[$field] ?? [];

        if (! is_array($paths)) {
            throw new InvalidArgumentException("The '{$field}' paths must be a list.");
        }

        return array_values(array_map(function (mixed $path) use ($field): string {
            if (! is_string($path)) {
                throw new InvalidArgumentException("The '{$field}' paths must be strings.");
            }

            return $this->safeRelativePath($path);
        }, $paths));
    }

    /**
     * The time budget of a request: the one it asks for (`budget`, in seconds), within the one of the server.
     *
     * @param  array<string, mixed>  $input
     */
    private function budget(array $input): float
    {
        if (! isset($input['budget']) || ! is_numeric($input['budget'])) {
            return $this->timeBudgetSeconds;
        }

        return max(self::MIN_TIME_BUDGET, min((float) $input['budget'], $this->timeBudgetSeconds));
    }

    private function isPreview(string $target): bool
    {
        return (bool) preg_match(self::PREVIEW_PATTERN, $target);
    }

    private function path(string $target, string $variant = ''): string
    {
        $folder = $this->isPreview($target) ? $this->spacePath.'/previews/'.$target : $this->spacePath.'/'.$target;

        return $folder.($variant === '' ? '' : '.'.$variant);
    }

    private function deleteArchive(string $target): void
    {
        if (is_file($this->path($target, 'zip'))) {
            unlink($this->path($target, 'zip'));
        }
    }

    /**
     * Refuse archive entries that would be unpacked outside of the release folder.
     */
    private function safeEntryName(string $name): string
    {
        $normalizedName = str_replace('\\', '/', $name);

        if ($normalizedName === '' || str_starts_with($normalizedName, '/') || preg_match('#(^|/)\.\.(/|$)#', $normalizedName) || str_contains($normalizedName, ':')) {
            throw new RuntimeException("Unsafe archive entry '{$name}'.");
        }

        return $name;
    }

    /**
     * Refuse paths of a release that point outside of its folder (or to the whole folder).
     */
    private function safeRelativePath(string $path): string
    {
        if (! preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$#', $path) || preg_match('#(^|/)\.{1,2}(/|$)#', $path)) {
            throw new InvalidArgumentException("Invalid path '{$path}': it must be inside the release.");
        }

        return $path;
    }

    /**
     * Delete a folder, deepest entries first, until the time budget runs out.
     */
    private function deleteDirectory(string $path, float $startedAt, float $budget): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            /** @var SplFileInfo $entry */
            if ($entry->isDir() && ! $entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }

            if (microtime(true) - $startedAt >= $budget) {
                return;
            }
        }

        rmdir($path);
    }

    /**
     * Delete a file or a whole folder at once: only for the small placeholders of a release replaced by kept paths.
     */
    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            $this->deleteDirectory($path, microtime(true), PHP_FLOAT_MAX);
        }
    }

    /**
     * Read the `KEY=value` lines of the `.env` file of a live preview (as written by the preview workflow).
     *
     * @return array<string, string>
     */
    private function readEnvironment(string $target): array
    {
        $environmentFile = $this->path($target).'/.env';

        if (! is_file($environmentFile)) {
            throw new RuntimeException("{$target} has no .env file.");
        }

        $environment = [];

        foreach (file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (! preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }

            $value = trim($matches[2]);

            if (preg_match('/^\'(.*)\'$/', $value, $quoted)) {
                $value = $quoted[1];
            } elseif (preg_match('/^"(.*)"$/', $value, $quoted)) {
                $value = stripcslashes($quoted[1]);
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }

            $environment[$matches[1]] = $value;
        }

        return $environment;
    }
}
