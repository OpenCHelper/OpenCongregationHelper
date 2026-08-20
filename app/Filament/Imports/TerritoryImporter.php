<?php

namespace App\Filament\Imports;

use App\Models\Territory;
use App\Models\TerritoryZone;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

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

    protected function beforeCreate(): void
    {
        $zone = $this->data['territoryZone'];

        if (TerritoryZone::where('name', $zone)->exists() && Territory::whereTerritoryZoneId($zone)->whereName($this->data['name'])) {
            throw new RowImportFailedException("Duplicate territory found with name '".$this->data['name']."' for zone '".$zone."'.");
        }

        $this->record->territory_zone_id = TerritoryZone::firstOrCreate(
            ['name' => $zone],
            ['name' => $zone],
        )->id;

        unset($this->record->territoryZone);
    }

    protected function beforeUpdate(): void
    {
        $this->record->territory_zone_id = TerritoryZone::firstOrCreate(
            ['name' => $this->data['territoryZone']],
            ['name' => $this->data['territoryZone']],
        )->id;

        unset($this->record->territoryZone);
    }

    protected function beforeFill(): void
    {
        Log::debug('beforeFill method', [
            'record' => $this->record,
            'data' => $this->data,
            'options' => $this->options,
        ]);

        if (filled($this->data['image_path'] ?? '') && ! $this->options['keep_image_links']) {
            $image_url = Uri::of($this->data['image_path'] ?? '');

            if (! in_array($image_url->scheme(), ['http', 'https'])) {
                throw new RowImportFailedException("Unsupported URI schema '".$image_url->scheme()."' for image '$image_url'.");
            }

            $new_image = 'territories/'.static::generateFilename('local', 'app/private/territories/', '.'.File::extension($image_url->path()));
            Log::debug('Downloading image...', [
                'source' => $image_url,
                'destination' => 'app/private/'.$new_image,
            ]);
            Http::sink(storage_path('app/private/'.$new_image))->get($image_url->getUri());

            $mimeType = Storage::mimeType($new_image);
            Log::debug('Downloaded image', [
                'source' => $image_url,
                'destination' => 'app/private/'.$new_image,
                'mimeType' => $mimeType,
            ]);

            if (! str_starts_with($mimeType, 'image/')) {
                throw new RowImportFailedException("Invalid MIME type '".$mimeType."' for URL '$image_url'.");
            }

            $this->data['image_path'] = $new_image;
        }
    }

    private static function generateFilename(string $disk = 'local', string $path = '', string $suffix = '')
    {
        $filename = Str::random(40);
        if (Storage::disk($disk)->exists($path.'/'.$filename.$suffix)) {
            $filename = static::generateFilename($disk, $path, $suffix);
        }

        return $filename.$suffix;
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Checkbox::make('updateExisting')
                ->label(__('import.territory.update_existing')),
            ToggleButtons::make('keep_image_links')
                ->label(__('import.territory.image_import.label'))
                ->boolean(
                    trueLabel: __('import.territory.image_import.store_link'),
                    falseLabel: __('import.territory.image_import.download_image'),
                )
                ->icons([
                    true => Heroicon::Link,
                    false => Heroicon::Photo,
                ])
                ->colors([
                    true => 'primary',
                    false => 'primary',
                ])
                ->default(true)
                ->grouped(),
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
