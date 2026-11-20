<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\ExportsInTheUserLocale;
use App\Models\Publication;
use App\Support\Translations;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export the publication list as the publication import reads it, so that the file can be imported again: the name in
 * the language of the user first, then the other columns, then the name in the other languages.
 */
class PublicationExporter extends Exporter
{
    use ExportsInTheUserLocale;

    protected static ?string $model = Publication::class;

    public static function getColumns(): array
    {
        [$locale, $otherLocales] = [Translations::getSortedLocales()[0], array_slice(Translations::getSortedLocales(), 1)];

        return [
            static::getNameColumn($locale),
            ExportColumn::make('code')
                ->label(fn (): string => __('import.publication.columns.code')),
            ExportColumn::make('category')
                ->label(fn (): string => __('import.publication.columns.category'))
                ->formatStateUsing(fn (Publication $record): string => $record->category->getLabel()),
            ExportColumn::make('renewal_months')
                ->label(fn (): string => __('import.publication.columns.renewal_months')),
            ExportColumn::make('is_active')
                ->label(fn (): string => __('import.publication.columns.is_active'))
                ->formatStateUsing(fn (Publication $record): string => $record->is_active ? 'yes' : 'no'),
            ...array_map(static::getNameColumn(...), $otherLocales),
        ];
    }

    /**
     * The name of the publication in a language, empty when it has no translation in it.
     */
    public static function getNameColumn(string $locale): ExportColumn
    {
        return ExportColumn::make("name_{$locale}")
            ->label(fn (): string => __('import.publication.columns.name', ['language' => Translations::getLocales()[$locale]]))
            ->state(fn (Publication $record): string => $record->getTranslation('name', $locale, false));
    }

    /**
     * In the order of the publication list.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->ordered();
    }
}
