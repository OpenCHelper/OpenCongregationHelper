<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\ImportsInTheUserLocale;
use App\Models\Poster;
use App\Models\StorageLocation;
use App\Support\Translations;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

/**
 * Import the posters from a spreadsheet with a row for each poster and storage place, e.g.:
 *
 *     Code   | Title          | Storage         | Quantity
 *     abc-01 | Example poster | Storage place A | 2
 *     abc-01 | Example poster | Storage place B | 3
 *
 * The headers are recognised by the label of each column in the language of the user (as Filament does) or by their
 * English names; the example file has them in the language of the user, so it is recognised again when imported.
 *
 * The rows of the same poster (same code, or same title without a code) update a single poster, so importing the
 * spreadsheet again updates the quantities instead of duplicating the posters. Missing storage places are created.
 */
class PosterImporter extends Importer
{
    use ImportsInTheUserLocale;

    protected static ?string $model = Poster::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label(fn (): string => __('import.poster.columns.code'))
                ->guess(['code', 'symbol'])
                ->exampleHeader(fn (): string => __('import.poster.columns.code'))
                ->examples(['abc-01', 'abc-01'])
                ->rules(['nullable', 'max:50']),
            ImportColumn::make('title')
                ->label(fn (): string => __('import.poster.columns.title'))
                ->guess(['title', 'name'])
                ->exampleHeader(fn (): string => __('import.poster.columns.title'))
                ->examples(fn (): array => array_fill(0, 2, __('import.poster.examples.title')))
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                // In the language chosen in the import window, keeping the translations in the other languages
                ->fillRecordUsing(fn (Poster $record, string $state, array $options) => $record->setTranslation('title', static::getTitleLocale($options), $state)),
            ImportColumn::make('storage')
                ->label(fn (): string => __('import.poster.columns.storage'))
                ->guess(['storage', 'storage place', 'location'])
                ->exampleHeader(fn (): string => __('import.poster.columns.storage'))
                // The same poster in two storage places, to show that each one has its row
                ->examples(fn (): array => [__('import.poster.examples.storage'), __('import.poster.examples.other_storage')])
                ->rules(['nullable', 'max:255'])
                // Not an attribute of the poster: saved as its copies in the storage place (see `afterSave()`)
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('quantity')
                ->label(fn (): string => __('import.poster.columns.quantity'))
                ->guess(['quantity', 'copies'])
                ->exampleHeader(fn (): string => __('import.poster.columns.quantity'))
                ->examples(['2', '3'])
                ->integer()
                ->rules(['nullable', 'integer', 'min:0', 'max:9999'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('notes')
                ->label(fn (): string => __('import.poster.columns.notes'))
                ->guess(['notes', 'note'])
                ->exampleHeader(fn (): string => __('import.poster.columns.notes'))
                ->examples(fn (): array => [__('import.poster.examples.notes'), ''])
                // Keep the notes of the poster when the column is empty (e.g. in the other rows of the same poster)
                ->fillRecordUsing(function (Poster $record, ?string $state): void {
                    if (filled($state)) {
                        $record->notes = $state;
                    }
                }),
        ];
    }

    /**
     * The options of the import window: the language of the titles in the file, the one of the user by default.
     *
     * @return array<Component>
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('locale')
                ->label(__('import.poster.options.locale'))
                ->helperText(__('import.poster.options.locale_helper'))
                ->options(Translations::getLocales())
                ->default(app()->getLocale())
                ->required()
                ->selectablePlaceholder(false),
        ];
    }

    /**
     * The language the titles are saved in: the one chosen in the import window, or the one of the user.
     *
     * @param  array<string, mixed>  $options
     */
    public static function getTitleLocale(array $options): string
    {
        $locale = $options['locale'] ?? null;

        return is_string($locale) && array_key_exists($locale, Translations::getLocales()) ? $locale : app()->getLocale();
    }

    /**
     * The poster with the same code or, without a code, with the same title in any language (the title of the row is
     * saved in the language chosen in the import window).
     */
    public function resolveRecord(): Poster
    {
        $code = trim((string) ($this->data['code'] ?? ''));

        if ($code !== '') {
            return Poster::query()->firstOrNew(['code' => $code]);
        }

        return Poster::query()
            ->whereNull('code')
            ->where(function (Builder $query): void {
                foreach (array_keys(Translations::getLocales()) as $locale) {
                    $query->orWhere("title->{$locale}", $this->data['title']);
                }
            })
            ->first() ?? new Poster;
    }

    /**
     * Fail the rows that the user who started the import is not allowed to import: Filament checks no policies
     * while importing.
     *
     * @throws RowImportFailedException
     */
    protected function afterValidate(): void
    {
        $gate = Gate::forUser($this->import->user);

        if ($gate->denies($this->record->exists ? 'update' : 'create', $this->record->exists ? $this->record : Poster::class)) {
            throw new RowImportFailedException(__('import.poster.errors.unauthorized', ['title' => $this->data['title']]));
        }

        $storage = $this->getStorageName();

        if ($storage !== null && $gate->denies('create', StorageLocation::class) && StorageLocation::query()->where('name', $storage)->doesntExist()) {
            throw new RowImportFailedException(__('import.poster.errors.storage_create_unauthorized', ['storage' => $storage]));
        }

        if ($storage === null && filled($this->data['quantity'] ?? null)) {
            throw new RowImportFailedException(__('import.poster.errors.quantity_without_storage', ['title' => $this->data['title']]));
        }
    }

    /**
     * Save the copies of the poster in the storage place of the row, replacing the previous quantity.
     */
    protected function afterSave(): void
    {
        $storage = $this->getStorageName();

        if ($storage === null) {
            return;
        }

        $location = StorageLocation::query()->firstOrCreate(['name' => $storage]);

        $this->record->stocks()->updateOrCreate(
            ['storage_location_id' => $location->getKey()],
            ['quantity' => (int) ($this->data['quantity'] ?? 0)],
        );
    }

    private function getStorageName(): ?string
    {
        $storage = trim((string) ($this->data['storage'] ?? ''));

        return $storage === '' ? null : $storage;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return static::withImportLocale($import, function () use ($import): string {
            $body = trans_choice('import.poster.notifications.completed.body', $import->successful_rows, [
                'count' => Number::format($import->successful_rows, locale: app()->getLocale()),
            ]);

            $failedRowsCount = $import->getFailedRowsCount();

            if ($failedRowsCount) {
                $body .= ' '.trans_choice('import.poster.notifications.completed.failed_rows', $failedRowsCount, [
                    'count' => Number::format($failedRowsCount, locale: app()->getLocale()),
                ]);
            }

            return $body;
        });
    }
}
