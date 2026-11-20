<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\ImportsInTheUserLocale;
use App\Models\StorageLocation;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Support\Facades\Gate;

/**
 * Import the storage places (e.g. the Kingdom Hall or a storage box), with a row for each one, as exported by
 * `StorageLocationExporter`: the rows with the name of an existing storage place update it.
 */
class StorageLocationImporter extends Importer
{
    use ImportsInTheUserLocale;

    protected static ?string $model = StorageLocation::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label(fn (): string => __('import.storage_location.columns.name'))
                ->exampleHeader(fn (): string => __('import.storage_location.columns.name'))
                ->examples(fn (): array => [__('import.storage_location.examples.name'), __('import.storage_location.examples.other_name')])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('notes')
                ->label(fn (): string => __('import.storage_location.columns.notes'))
                ->exampleHeader(fn (): string => __('import.storage_location.columns.notes'))
                ->examples(fn (): array => [__('import.storage_location.examples.notes'), ''])
                ->rules(['nullable', 'max:65535']),
        ];
    }

    /**
     * The storage place with the same name.
     */
    public function resolveRecord(): StorageLocation
    {
        return StorageLocation::query()->firstOrNew(['name' => trim((string) $this->data['name'])]);
    }

    /**
     * Fail the rows that the user who started the import is not allowed to import: Filament checks no policies
     * while importing.
     *
     * @throws RowImportFailedException
     */
    protected function afterValidate(): void
    {
        if (Gate::forUser($this->import->user)->denies($this->record->exists ? 'update' : 'create', $this->record->exists ? $this->record : StorageLocation::class)) {
            throw new RowImportFailedException(__('import.storage_location.errors.unauthorized', ['name' => $this->data['name']]));
        }
    }
}
