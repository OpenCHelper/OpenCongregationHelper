<?php

namespace App\Filament\Exports\Concerns;

use App\Support\SpreadsheetFormula;
use App\Support\UserLocale;
use Filament\Actions\Action;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

/**
 * Export the rows, and notify the result, in the language of the user who started the export.
 *
 * The export is processed (and its notification sent) by the queue worker, which runs in the default locale of the
 * application: without this, the values translated while exporting (e.g. the categories) and the notification would
 * not be in their language. The headers of the file are already in their language, as chosen in the export window.
 *
 * The cells of the CSV file that a spreadsheet would run as a formula (starting with `=`, `+`, `-`, `@`, a tab or a carriage return,
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
     * The rows of the Excel file, built from the same ones of the CSV file. Every text is written as a text cell, which
     * a spreadsheet never runs as a formula: OpenSpout would otherwise write the texts starting with `=` as formulas.
     * So there the quote of the CSV file isn't needed, and it isn't shown.
     *
     * @param  array<mixed>  $values
     */
    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(
            fn (mixed $value): Cell => is_string($value) && $value !== ''
                ? new StringCell(SpreadsheetFormula::unescape($value), $style)
                : Cell::fromValue($value, $style),
            array_values($values),
        ), $style);
    }

    /**
     * Show as text a cell that a spreadsheet would run as a formula (see `SpreadsheetFormula`).
     */
    public static function escapeFormula(mixed $value): mixed
    {
        return SpreadsheetFormula::escape($value);
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
