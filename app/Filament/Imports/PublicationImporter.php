<?php

namespace App\Filament\Imports;

use App\Enums\PublicationCategory;
use App\Filament\Imports\Concerns\ImportsInTheUserLocale;
use App\Models\Publication;
use App\Support\Translations;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Support\Facades\Gate;

/**
 * Import the publication list, with a row for each publication, as exported by `PublicationExporter`: the name in the
 * language of the user first, then the other columns, then the name in the other languages.
 *
 * The rows with the code of an existing publication update it; the names left empty keep their translation. The
 * category can be given by its name in any language of the application or by its key (e.g. `books`).
 */
class PublicationImporter extends Importer
{
    use ImportsInTheUserLocale;

    protected static ?string $model = Publication::class;

    public static function getColumns(): array
    {
        [$locale, $otherLocales] = [Translations::getSortedLocales()[0], array_slice(Translations::getSortedLocales(), 1)];

        return [
            static::getNameColumn($locale),
            ImportColumn::make('code')
                ->label(fn (): string => __('import.publication.columns.code'))
                ->exampleHeader(fn (): string => __('import.publication.columns.code'))
                ->examples(['abc', 'xyz'])
                ->requiredMapping()
                ->rules(['required', 'max:50']),
            ImportColumn::make('category')
                ->label(fn (): string => __('import.publication.columns.category'))
                ->exampleHeader(fn (): string => __('import.publication.columns.category'))
                ->examples(fn (): array => [PublicationCategory::Books->getLabel(), PublicationCategory::Watchtower->getLabel()])
                ->castStateUsing(fn (?string $state): ?PublicationCategory => static::resolveCategory($state))
                // Empty: the current category, or "Other" for a new publication
                ->ignoreBlankState(),
            ImportColumn::make('renewal_months')
                ->label(fn (): string => __('import.publication.columns.renewal_months'))
                ->exampleHeader(fn (): string => __('import.publication.columns.renewal_months'))
                ->examples(['', '1'])
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1', 'max:24']),
            ImportColumn::make('is_active')
                ->label(fn (): string => __('import.publication.columns.is_active'))
                ->exampleHeader(fn (): string => __('import.publication.columns.is_active'))
                ->examples(['yes', 'no'])
                ->boolean()
                // Empty: as it is, or offered for a new publication
                ->ignoreBlankState(),
            ...array_map(static::getNameColumn(...), $otherLocales),
        ];
    }

    /**
     * The name of the publication in a language: saved as its translation, keeping the others when it is empty.
     */
    public static function getNameColumn(string $locale): ImportColumn
    {
        $label = fn (): string => __('import.publication.columns.name', ['language' => Translations::getLocales()[$locale]]);

        return ImportColumn::make("name_{$locale}")
            ->label($label)
            ->exampleHeader($label)
            ->examples(fn (): array => [__('import.publication.examples.name', [], $locale), __('import.publication.examples.other_name', [], $locale)])
            ->rules(['nullable', 'max:255'])
            ->fillRecordUsing(function (Publication $record, ?string $state) use ($locale): void {
                if (filled($state)) {
                    $record->setTranslation('name', $locale, trim($state));
                }
            });
    }

    /**
     * The category with the given key (e.g. `books`) or name in any language of the application, if any.
     */
    public static function resolveCategory(?string $state): ?PublicationCategory
    {
        $state = mb_strtolower(trim((string) $state));

        if ($state === '') {
            return null;
        }

        foreach (PublicationCategory::cases() as $category) {
            $names = [$category->value, ...array_map(
                fn (string $locale): string => mb_strtolower(__("filament-resources.publication.categories.{$category->value}", [], $locale)),
                array_keys(Translations::getLocales()),
            )];

            if (in_array($state, $names, true)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * The publication with the same code.
     */
    public function resolveRecord(): Publication
    {
        return Publication::query()->firstOrNew(['code' => trim((string) $this->data['code'])]);
    }

    /**
     * Fail the rows that are not complete, or that the user who started the import is not allowed to import:
     * Filament checks no policies while importing.
     *
     * @throws RowImportFailedException
     */
    protected function afterValidate(): void
    {
        $code = $this->data['code'];

        if (Gate::forUser($this->import->user)->denies($this->record->exists ? 'update' : 'create', $this->record->exists ? $this->record : Publication::class)) {
            throw new RowImportFailedException(__('import.publication.errors.unauthorized', ['code' => $code]));
        }

        $names = array_filter(array_map(
            fn (string $locale): ?string => $this->data["name_{$locale}"] ?? null,
            array_keys(Translations::getLocales()),
        ), 'filled');

        if (! $this->record->exists && $names === []) {
            throw new RowImportFailedException(__('import.publication.errors.name_missing', ['code' => $code]));
        }

        if (filled($this->originalData['category'] ?? null) && $this->data['category'] === null) {
            throw new RowImportFailedException(__('import.publication.errors.category_unknown', ['code' => $code, 'category' => $this->originalData['category']]));
        }
    }

    /**
     * The defaults of a new publication: in the "Other" category and offered, unless the row says otherwise.
     */
    protected function beforeSave(): void
    {
        $this->record->category ??= PublicationCategory::Other;
        $this->record->is_active ??= true;
    }
}
