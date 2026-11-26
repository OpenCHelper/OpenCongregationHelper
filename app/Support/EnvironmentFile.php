<?php

namespace App\Support;

use Illuminate\Support\Env;
use RuntimeException;

/**
 * The `.env` file of the installation, as filled in by the setup wizard: it only adds the values that are missing (not
 * set, or empty), and never changes the ones already set, in the file or in the environment of the server.
 */
class EnvironmentFile
{
    public function __construct(private ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? app()->environmentFilePath();
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function isWritable(): bool
    {
        return $this->exists() ? is_writable($this->path()) : is_writable(dirname($this->path()));
    }

    /**
     * Whether a variable has no value, neither in the environment of the server nor in the file.
     */
    public function isMissing(string $key): bool
    {
        $value = Env::get($key);

        return ($value === null || $value === '') && blank($this->fileValues()[$key] ?? null);
    }

    /**
     * The variables of the list without a value.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function missing(array $keys): array
    {
        return array_values(array_filter($keys, fn (string $key): bool => $this->isMissing($key)));
    }

    /**
     * Write the given values of the variables that are missing, skipping the others: an empty line of the variable
     * (e.g. `DB_HOST=`) is filled in, otherwise the variable is added at the end of the file.
     *
     * @param  array<string, string>  $values
     * @return list<string> The variables written.
     */
    public function writeMissing(array $values): array
    {
        $values = array_filter(
            $values,
            fn (string $value, string $key): bool => $value !== '' && $this->isMissing($key),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($values === []) {
            return [];
        }

        if (! $this->isWritable()) {
            throw new RuntimeException("The {$this->path()} file is not writable.");
        }

        $contents = $this->exists() ? (string) file_get_contents($this->path()) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);
            $emptyLine = '/^'.preg_quote($key, '/').'=\s*(""|\'\')?\s*(#.*)?$/m';

            if (preg_match($emptyLine, $contents)) {
                $contents = (string) preg_replace($emptyLine, addcslashes($line, '\\$'), $contents, 1);
            } else {
                $contents = rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";
            }
        }

        file_put_contents($this->path(), $contents);

        return array_keys($values);
    }

    /**
     * Whether a variable is set by the environment of the server rather than by the file: writing the file would not
     * change it, as the environment of the server comes first.
     */
    public function isSetOutsideFile(string $key): bool
    {
        $value = Env::get($key);

        return $value !== null && $value !== '' && ! array_key_exists($key, $this->fileValues());
    }

    /**
     * Write the value of a variable, replacing its line when the file has one, otherwise adding it at the end.
     */
    public function set(string $key, string $value): void
    {
        if (! $this->isWritable()) {
            throw new RuntimeException("The {$this->path()} file is not writable.");
        }

        $contents = $this->exists() ? (string) file_get_contents($this->path()) : '';
        $line = $key.'='.self::quote($value);
        $existingLine = '/^'.preg_quote($key, '/').'=.*$/m';

        $contents = preg_match($existingLine, $contents)
            ? (string) preg_replace($existingLine, addcslashes($line, '\\$'), $contents, 1)
            : rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";

        file_put_contents($this->path(), $contents);
    }

    /**
     * Remove the line of a variable from the file, if it has one.
     */
    public function remove(string $key): void
    {
        if (! $this->exists()) {
            return;
        }

        if (! $this->isWritable()) {
            throw new RuntimeException("The {$this->path()} file is not writable.");
        }

        $contents = (string) file_get_contents($this->path());

        file_put_contents($this->path(), (string) preg_replace('/^'.preg_quote($key, '/').'=.*(\R|$)/m', '', $contents));
    }

    /**
     * The values of the variables written in the file.
     *
     * @return array<string, string>
     */
    public function fileValues(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $values = [];

        foreach (file($this->path(), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $matches)) {
                $values[$matches[1]] = self::unquote($matches[2]);
            }
        }

        return $values;
    }

    /**
     * Quote a value, so that spaces, `#` and `$` are kept as they are.
     */
    public static function quote(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private static function unquote(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $value, $matches)) {
            return stripcslashes($matches[1]);
        }

        if (preg_match("/^'([^']*)'/", $value, $matches)) {
            return $matches[1];
        }

        return trim((string) preg_replace('/\s+#.*$/', '', $value));
    }
}
