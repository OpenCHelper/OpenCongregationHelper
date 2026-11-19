<?php

namespace App\Filament\Imports\Concerns;

use Closure;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

/**
 * Import one file of a kind at a time, and fail with a clear reason the rows whose record was created meanwhile.
 *
 * Filament already processes the chunks of an import one at a time (see `Importer::getJobMiddleware()`), but two
 * imports started together (e.g. two users importing posters) could still both find no record with a code (or name,
 * or title for a poster without a code) and both create it. Here the imports of the same importer wait for each other
 * too, so that this can't happen. Should another writer still hit a unique index meanwhile, the row fails with a
 * reason telling to import the file again (which updates the records instead of duplicating them), rather than with a
 * database error, and the other rows of the chunk are kept.
 */
trait ReportsDuplicateRecords
{
    /**
     * The jobs of Filament, plus one import of this kind at a time (among all the users).
     *
     * @return array<int, object>
     */
    public function getJobMiddleware(): array
    {
        return [
            ...parent::getJobMiddleware(),
            (new WithoutOverlapping('imports:'.static::class))->releaseAfter(5)->expireAfter(600),
        ];
    }

    /**
     * @throws RowImportFailedException
     */
    public function saveRecord(): void
    {
        // In a savepoint, so that the other rows of the chunk are kept
        static::reportDuplicates(fn () => parent::saveRecord());
    }

    /**
     * Run the callback in a savepoint; the row fails with a clear reason when another writer saved the same unique
     * values at the same time.
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
}
