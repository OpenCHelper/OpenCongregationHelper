<?php

namespace App\Filament\Imports\Concerns;

use Closure;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Fail with a clear reason the rows whose record was created meanwhile by another chunk of the same import.
 *
 * The chunks of an import are processed in parallel by the queue workers: two of them can both find no record with a
 * code (or name) and both create it, and the second one hits the unique index. The row is then failed, with a reason
 * telling to import the file again (which updates the records instead of duplicating them), rather than with a
 * database error. The import actions read up to 1000 rows per chunk, so that this is rare.
 *
 * The records without a unique value (e.g. a poster without a code, found by its title) can't be told apart this
 * way: their rows must not be more than 1000 apart in the file, or two chunks could both create them.
 */
trait ReportsDuplicateRecords
{
    /**
     * @throws RowImportFailedException
     */
    public function saveRecord(): void
    {
        // In a savepoint, so that the other rows of the chunk are kept
        static::reportDuplicates(fn () => parent::saveRecord());
    }

    /**
     * Run the callback in a savepoint; the row fails with a clear reason when another chunk of the import wrote the
     * same unique values at the same time.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     *
     * @throws RowImportFailedException
     */
    protected static function reportDuplicates(Closure $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (UniqueConstraintViolationException) {
            throw new RowImportFailedException(__('import.errors.duplicate'));
        }
    }

    /**
     * The record with the given attributes, created if missing; the row fails with a clear reason when another chunk
     * of the import created it at the same time.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $attributes
     * @return TModel
     *
     * @throws RowImportFailedException
     */
    protected static function firstOrCreateOrReport(Builder $query, array $attributes): Model
    {
        return static::reportDuplicates(fn (): Model => (clone $query)->firstOrCreate($attributes));
    }
}
