<?php

namespace App\Filament\Imports\Concerns;

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
 */
trait ReportsDuplicateRecords
{
    /**
     * @throws RowImportFailedException
     */
    public function saveRecord(): void
    {
        try {
            // In a savepoint, so that the other rows of the chunk are kept
            DB::transaction(fn () => parent::saveRecord());
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
        try {
            return DB::transaction(fn (): Model => (clone $query)->firstOrCreate($attributes));
        } catch (UniqueConstraintViolationException) {
            throw new RowImportFailedException(__('import.errors.duplicate'));
        }
    }
}
