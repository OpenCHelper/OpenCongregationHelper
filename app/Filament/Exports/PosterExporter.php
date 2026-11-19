<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\ExportsInTheUserLocale;
use App\Models\Poster;
use App\Support\Translations;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export the posters as the poster import reads them, so that the file can be imported again: a row for each poster
 * and storage place (a single row, without storage place, for the posters kept nowhere).
 */
class PosterExporter extends Exporter
{
    use ExportsInTheUserLocale;

    protected static ?string $model = Poster::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code')
                ->label(fn (): string => __('import.poster.columns.code')),
            ExportColumn::make('title')
                ->label(fn (): string => __('import.poster.columns.title'))
                ->state(fn (Poster $record, array $options): string => $record->getTranslation('title', Translations::resolveLocale($options['locale'] ?? null))),
            ExportColumn::make('export_storage')
                ->label(fn (): string => __('import.poster.columns.storage')),
            ExportColumn::make('export_quantity')
                ->label(fn (): string => __('import.poster.columns.quantity')),
            ExportColumn::make('notes')
                ->label(fn (): string => __('import.poster.columns.notes')),
        ];
    }

    /**
     * The options of the export window: the language of the titles, the one of the user by default.
     *
     * @return array<Component>
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('locale')
                ->label(__('export.options.locale_titles'))
                ->helperText(__('export.options.locale_helper'))
                ->options(Translations::getLocales())
                ->default(app()->getLocale())
                ->required()
                ->selectablePlaceholder(false),
        ];
    }

    /**
     * A row for each storage place of the poster: the records are found by their key, so each poster brings all its
     * rows.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->leftJoin('poster_stocks', 'poster_stocks.poster_id', '=', 'posters.id')
            ->leftJoin('storage_locations', 'storage_locations.id', '=', 'poster_stocks.storage_location_id')
            ->select([
                'posters.*',
                'storage_locations.name as export_storage',
                'poster_stocks.quantity as export_quantity',
            ]);
    }
}
