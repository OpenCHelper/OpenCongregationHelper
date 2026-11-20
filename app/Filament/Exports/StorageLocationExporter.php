<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\ExportsInTheUserLocale;
use App\Models\StorageLocation;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

/**
 * Export the storage places as the storage import reads them, so that the file can be imported again.
 */
class StorageLocationExporter extends Exporter
{
    use ExportsInTheUserLocale;

    protected static ?string $model = StorageLocation::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label(fn (): string => __('import.storage_location.columns.name')),
            ExportColumn::make('notes')
                ->label(fn (): string => __('import.storage_location.columns.notes')),
        ];
    }
}
