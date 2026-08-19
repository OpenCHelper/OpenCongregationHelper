<?php

namespace App\Filament\Imports;

use App\Models\Territory;
use App\Models\TerritoryZone;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

class TerritoryImporter extends Importer
{
    protected static ?string $model = Territory::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('description'),
            ImportColumn::make('image_path')
                ->helperText('Currently, only absolute URLs are supported.')
                ->example('https://yavuzceliker.github.io/sample-images/image-6.jpg'),
            ImportColumn::make('geojson'),
            ImportColumn::make('notes'),
            ImportColumn::make('is_commercial')
                ->label('Is commercial?')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('territoryZone')
                // ->relationship(resolveUsing: 'name')
                ->rules(['required']),
            // ImportColumn::make('created_by')
            //     ->numeric()
            //     ->rules(['integer']),
        ];
    }

    public function resolveRecord(): Territory
    {
        if ($this->options['updateExisting'] ?? false) {
            return Territory::firstOrNew([
                'name' => $this->data['name'],
            ]);
        }

        return new Territory;
    }

    protected function beforeCreate(): Territory
    {
        $record = $this->record;
        $data = $this->data;

        Log::debug('beforeCreate method', [
            'record' => $record,
            'data' => $data,
        ]);
        $zone = TerritoryZone::firstOrCreate(
            ['name' => $data['territoryZone']],
            [
                'name' => $data['territoryZone'],
            ]
        );
        Log::debug('Found territory zone', [
            'requested' => $data['territoryZone'],
            'found' => $zone,
        ]);
        $record->territory_zone_id = $zone->id;

        unset($record->territoryZone);

        return $record;
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Checkbox::make('updateExisting')
                ->label('Update existing records'),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your territory import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
