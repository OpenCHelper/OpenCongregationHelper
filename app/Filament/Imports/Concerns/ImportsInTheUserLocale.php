<?php

namespace App\Filament\Imports\Concerns;

use App\Support\UserLocale;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * Process the rows of an import, and notify its result, in the language of the user who started it.
 *
 * The import is processed (and its notification sent) by the queue worker, which runs in the default locale of the
 * application: without this, the reasons of the failed rows and the notification would not be in their language.
 */
trait ImportsInTheUserLocale
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data): void
    {
        static::withImportLocale($this->import, fn () => parent::__invoke($data));
    }

    /**
     * A generic summary of the import, for the importers without their own.
     */
    public static function getCompletedNotificationBody(Import $import): string
    {
        return static::withImportLocale($import, function () use ($import): string {
            $body = trans_choice('import.notifications.completed.body', $import->successful_rows, [
                'count' => Number::format($import->successful_rows, locale: app()->getLocale()),
            ]);

            $failedRowsCount = $import->getFailedRowsCount();

            if ($failedRowsCount) {
                $body .= ' '.trans_choice('import.notifications.completed.failed_rows', $failedRowsCount, [
                    'count' => Number::format($failedRowsCount, locale: app()->getLocale()),
                ]);
            }

            return $body;
        });
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return static::withImportLocale($import, fn (): string => parent::getCompletedNotificationTitle($import));
    }

    /**
     * Label the action that downloads the failed rows in the language of the user who started the import too.
     */
    public static function modifyCompletedNotification(Notification $notification, Import $import): Notification
    {
        $failedRowsCount = $import->getFailedRowsCount();

        if (! $failedRowsCount) {
            return $notification;
        }

        foreach ($notification->getActions() as $action) {
            if ($action instanceof Action && $action->getName() === 'downloadFailedRowsCsv') {
                $action->label(static::withImportLocale($import, fn (): string => trans_choice(
                    'filament-actions::import.notifications.completed.actions.download_failed_rows_csv.label',
                    $failedRowsCount,
                    ['count' => Number::format($failedRowsCount, locale: app()->getLocale())],
                )));
            }
        }

        return $notification;
    }

    /**
     * Run the callback in the preferred locale of the user who started the import, falling back to the
     * current locale.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    protected static function withImportLocale(Import $import, Closure $callback): mixed
    {
        return UserLocale::run($import->user, $callback);
    }
}
