<?php

namespace App\Console\Commands;

use Illuminate\Database\Connection;
use Illuminate\Database\Console\WipeCommand;
use RuntimeException;

/**
 * `db:wipe` (also run by `migrate:fresh`) that only drops the tables of this installation.
 *
 * Laravel drops every table of the database, whatever its prefix, which wipes the other installations sharing the
 * database (e.g. the TEST environment and the PR previews). Instead:
 *
 *  - with a table prefix (`DB_PREFIX`), only the tables (and views) starting with it are dropped;
 *  - without one, the command refuses to run when the database holds the tables of other installations, recognised
 *    by their prefixed `migrations` table (e.g. `p12_migrations`), since their tables could not be told apart.
 *
 * It replaces the framework command through the container (see `AppServiceProvider::register()`).
 */
class PrefixAwareWipeCommand extends WipeCommand
{
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $otherPrefixes = $this->otherInstallationsPrefixes($this->input->getOption('database'));

        // Thrown, not returned: `migrate:fresh` ignores the exit code of `db:wipe` and would migrate anyway
        if ($otherPrefixes !== []) {
            throw new RuntimeException(sprintf(
                'The database also holds the tables of other installations (prefixed with %s): set a table prefix '
                .'(DB_PREFIX) for this installation too, otherwise their tables would be dropped as well.',
                implode(', ', $otherPrefixes),
            ));
        }

        return parent::handle();
    }

    /**
     * Drop the tables of this installation: all of them when the tables are not prefixed.
     *
     * @param  string|null  $database
     */
    protected function dropAllTables($database): void
    {
        $connection = $this->connection($database);
        $prefix = $connection->getTablePrefix();

        if ($prefix === '') {
            parent::dropAllTables($database);

            return;
        }

        $schema = $connection->getSchemaBuilder();
        $tables = array_filter($this->tableNames($connection), fn (string $table): bool => str_starts_with($table, $prefix));

        $schema->withoutForeignKeyConstraints(function () use ($schema, $tables, $prefix): void {
            foreach ($tables as $table) {
                // The schema builder adds the prefix back
                $schema->drop(substr($table, strlen($prefix)));
            }
        });
    }

    /**
     * Drop the views of this installation: all of them when the tables are not prefixed.
     *
     * @param  string|null  $database
     */
    protected function dropAllViews($database): void
    {
        $connection = $this->connection($database);
        $prefix = $connection->getTablePrefix();

        if ($prefix === '') {
            parent::dropAllViews($database);

            return;
        }

        $schema = $connection->getSchemaBuilder();

        foreach ($schema->getViews($schema->getCurrentSchemaListing()) as $view) {
            if (str_starts_with($view['name'], $prefix)) {
                $connection->statement('drop view '.$connection->getSchemaGrammar()->wrapTable($view['name'], ''));
            }
        }
    }

    /**
     * Drop the types (Postgres only), unless the tables are prefixed: types cannot be told apart by prefix.
     *
     * @param  string|null  $database
     */
    protected function dropAllTypes($database): void
    {
        if ($this->connection($database)->getTablePrefix() !== '') {
            $this->components->warn('The types are not dropped when the tables are prefixed: they may belong to other installations.');

            return;
        }

        parent::dropAllTypes($database);
    }

    /**
     * Prefixes of the other installations whose tables are in the database, when this one has no prefix.
     *
     * @return list<string>
     */
    private function otherInstallationsPrefixes(?string $database): array
    {
        $connection = $this->connection($database);

        if ($connection->getTablePrefix() !== '') {
            return [];
        }

        $prefixes = [];

        foreach ($this->tableNames($connection) as $table) {
            if (preg_match('/^(.+_)migrations$/', $table, $matches)) {
                $prefixes[] = $matches[1];
            }
        }

        sort($prefixes);

        return array_values(array_unique($prefixes));
    }

    /**
     * Names (with the prefix) of the tables of the current database.
     *
     * @return list<string>
     */
    private function tableNames(Connection $connection): array
    {
        $schema = $connection->getSchemaBuilder();

        return array_values($schema->getTableListing($schema->getCurrentSchemaListing(), schemaQualified: false));
    }

    private function connection(?string $database): Connection
    {
        return $this->laravel['db']->connection($database);
    }
}
