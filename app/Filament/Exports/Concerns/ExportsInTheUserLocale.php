<?php

namespace App\Filament\Exports\Concerns;

use App\Support\UserLocale;
use Filament\Actions\Action;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * Export the rows, and notify the result, in the language of the user who started the export.
 *
 * The export is processed (and its notification sent) by the queue worker, which runs in the default locale of the
 * application: without this, the values translated while exporting (e.g. the categories) and the notification would
 * not be in their language. The headers of the file are already in their language, as chosen in the export window.
 *
 * The cells that a spreadsheet would run as a formula (starting with `=`, `+`, `-`, `@`, a tab or a carriage return,
 * e.g. typed in a title) are prefixed with a quote, so that they are shown as text.
 */
trait ExportsInTheUserLocale
{
    /**
     * @return array<mixed>
     */
    public function __invoke(Model $record): array
    {
        // The columns cache their state by the key of the record: a record exported in more rows (e.g. a poster in
        // more storage places) would repeat the state of its first row
        foreach ($this->getCachedColumns() as $column) {
            $column->clearCachedState();
        }

        return array_map(
            static::escapeFormula(...),
            UserLocale::run($this->export->user, fn (): array => parent::__invoke($record)),
        );
    }

    /**
     * Show as text a cell that a spreadsheet would run as a formula; numbers (e.g. negative ones) are left as they are.
     */
    public static function escapeFormula(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value) || ! in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return $value;
        }

        return "'".$value;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return UserLocale::run($export->user, function () use ($export): string {
            $body = trans_choice('export.notifications.completed.body', $export->successful_rows, [
                'count' => Number::format($export->successful_rows, locale: app()->getLocale()),
            ]);

            $failedRowsCount = $export->getFailedRowsCount();

            if ($failedRowsCount) {
                $body .= ' '.trans_choice('export.notifications.completed.failed_rows', $failedRowsCount, [
                    'count' => Number::format($failedRowsCount, locale: app()->getLocale()),
                ]);
            }

            return $body;
        });
    }

    public static function modifyCompletedNotification(Notification $notification, Export $export): Notification
    {
        UserLocale::run($export->user, function () use ($notification, $export): void {
            $notification
                ->title(static::getCompletedNotificationTitle($export))
                ->body(static::getCompletedNotificationBody($export));

            foreach ($notification->getActions() as $action) {
                if ($action instanceof Action && str_starts_with($action->getName(), 'download_')) {
                    $action->label(__("filament-actions::export.notifications.completed.actions.{$action->getName()}.label"));
                }
            }
        });

        return $notification;
    }
}
