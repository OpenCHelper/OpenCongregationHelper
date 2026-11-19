<?php

namespace App\Filament\Imports;

use App\Filament\Imports\Concerns\ImportsInTheUserLocale;
use App\Models\Territory;
use App\Models\TerritoryZone;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

class TerritoryImporter extends Importer
{
    use ImportsInTheUserLocale;

    /**
     * The maximum size of an image downloaded during the import, in bytes.
     */
    private const MAX_IMAGE_SIZE = 10 * 1024 * 1024;

    protected static ?string $model = Territory::class;

    public static function getColumns(): array
    {
        return [
            EnglishImportColumn::make('name')
                ->label(fn (): string => __('import.territory.columns.name'))
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            EnglishImportColumn::make('description')
                ->label(fn (): string => __('import.territory.columns.description')),
            EnglishImportColumn::make('image_path')
                ->label(fn (): string => __('import.territory.columns.image_path'))
                ->helperText(fn (): string => __('import.territory.columns.image_path_helper'))
                ->example('https://yavuzceliker.github.io/sample-images/image-6.jpg'),
            EnglishImportColumn::make('geojson')
                ->label(fn (): string => __('import.territory.columns.geojson')),
            EnglishImportColumn::make('notes')
                ->label(fn (): string => __('import.territory.columns.notes')),
            EnglishImportColumn::make('is_commercial')
                ->label(fn (): string => __('import.territory.columns.is_commercial'))
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            EnglishImportColumn::make('territoryZone')
                ->label(fn (): string => __('import.territory.columns.territory_zone'))
                // ->relationship(resolveUsing: 'name')
                ->rules(['required']),
            // EnglishImportColumn::make('created_by')
            //     ->numeric()
            //     ->rules(['integer']),
        ];
    }

    public function resolveRecord(): Territory
    {
        if ($this->options['updateExisting'] ?? false) {
            $zone = TerritoryZone::where('name', $this->data['territoryZone'] ?? null)->first();

            // Territory names are unique per zone, so the zone is part of the lookup
            if ($zone) {
                return Territory::firstOrNew([
                    'name' => $this->data['name'],
                    'territory_zone_id' => $zone->id,
                ]);
            }
        }

        return new Territory;
    }

    /**
     * Fail the rows that the user who started the import is not allowed to import. Filament checks no
     * policies while importing, so updating an existing territory requires the permission to update it,
     * and importing into a zone that does not exist yet requires the permission to create zones.
     *
     * @throws RowImportFailedException
     */
    protected function afterValidate(): void
    {
        $gate = Gate::forUser($this->import->user);

        if ($this->record->exists && $gate->denies('update', $this->record)) {
            throw new RowImportFailedException(__('import.territory.errors.update_unauthorized', [
                'name' => $this->data['name'],
                'zone' => $this->data['territoryZone'],
            ]));
        }

        if ($gate->denies('create', TerritoryZone::class) && TerritoryZone::where('name', $this->data['territoryZone'])->doesntExist()) {
            throw new RowImportFailedException(__('import.territory.errors.zone_create_unauthorized', [
                'zone' => $this->data['territoryZone'],
            ]));
        }
    }

    protected function beforeCreate(): void
    {
        $zoneName = $this->data['territoryZone'];
        $zone = TerritoryZone::firstOrCreate(['name' => $zoneName]);

        $isDuplicate = Territory::withTrashed()
            ->where('territory_zone_id', $zone->id)
            ->where('name', $this->data['name'])
            ->exists();

        if ($isDuplicate) {
            throw new RowImportFailedException(__('import.territory.errors.duplicate', [
                'name' => $this->data['name'],
                'zone' => $zoneName,
            ]));
        }

        $this->record->territory_zone_id = $zone->id;

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
        if (filled($this->data['image_path'] ?? null) && ! ($this->options['keep_image_links'] ?? true)) {
            $this->data['image_path'] = $this->downloadImage((string) $this->data['image_path']);
        }
    }

    /**
     * Download the image at the given URL into the application storage.
     *
     * @return string The path of the downloaded image on the `local` disk.
     *
     * @throws RowImportFailedException When the image cannot be downloaded or is not valid.
     */
    private function downloadImage(string $url): string
    {
        $uri = Uri::of($url);

        if (! in_array($uri->scheme(), ['http', 'https'], true)) {
            throw new RowImportFailedException(__('import.territory.errors.image_unsupported_scheme', [
                'scheme' => $uri->scheme(),
                'url' => $url,
            ]));
        }

        static::ensurePublicHost((string) $uri->host(), $url);

        $disk = Storage::disk('local');
        $disk->makeDirectory('territories');
        $downloadPath = 'territories/'.Str::random(40).'.download';

        try {
            $response = Http::connectTimeout(10)
                ->timeout(30)
                ->withOptions([
                    // Abort as soon as the server announces a file that is too large
                    'on_headers' => function (ResponseInterface $response): void {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_IMAGE_SIZE) {
                            throw new RuntimeException(__('import.territory.errors.image_larger_than', [
                                'size' => Number::fileSize(self::MAX_IMAGE_SIZE),
                            ]));
                        }
                    },
                    // Redirects must not lead to private hosts either
                    'allow_redirects' => [
                        'max' => 5,
                        'protocols' => ['http', 'https'],
                        'on_redirect' => fn ($request, $response, UriInterface $redirectUri) => static::ensurePublicHost($redirectUri->getHost(), $url),
                    ],
                ])
                ->sink($disk->path($downloadPath))
                ->get($url);
        } catch (Throwable $exception) {
            $disk->delete($downloadPath);

            throw new RowImportFailedException(__('import.territory.errors.image_download_failed', [
                'url' => $url,
                'reason' => $exception->getMessage(),
            ]));
        }

        try {
            if ($response->failed()) {
                throw new RowImportFailedException(__('import.territory.errors.image_http_error', [
                    'url' => $url,
                    'status' => $response->status(),
                ]));
            }

            if ($disk->size($downloadPath) > self::MAX_IMAGE_SIZE) {
                throw new RowImportFailedException(__('import.territory.errors.image_too_large', [
                    'url' => $url,
                    'size' => Number::fileSize(self::MAX_IMAGE_SIZE),
                ]));
            }

            $mimeType = (string) $disk->mimeType($downloadPath);

            if (! str_starts_with($mimeType, 'image/')) {
                throw new RowImportFailedException(__('import.territory.errors.image_invalid_mime_type', [
                    'mime_type' => $mimeType,
                    'url' => $url,
                ]));
            }
        } catch (RowImportFailedException $exception) {
            $disk->delete($downloadPath);

            throw $exception;
        }

        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? 'img';
        $imagePath = 'territories/'.Str::random(40).'.'.$extension;
        $disk->move($downloadPath, $imagePath);

        return $imagePath;
    }

    /**
     * Ensure that the given host does not point to a private, loopback or reserved address,
     * so that the import cannot be used to reach internal services.
     *
     * @throws RowImportFailedException
     */
    private static function ensurePublicHost(string $host, string $url): void
    {
        $host = trim($host, '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : (gethostbynamel($host) ?: []);

        if ($addresses === []) {
            throw new RowImportFailedException(__('import.territory.errors.image_unresolvable_host', ['url' => $url]));
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RowImportFailedException(__('import.territory.errors.image_private_host', ['url' => $url]));
            }
        }
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
        return static::withImportLocale($import, function () use ($import): string {
            $body = trans_choice('import.territory.notifications.completed.body', $import->successful_rows, [
                'count' => Number::format($import->successful_rows, locale: app()->getLocale()),
            ]);

            $failedRowsCount = $import->getFailedRowsCount();

            if ($failedRowsCount) {
                $body .= ' '.trans_choice('import.territory.notifications.completed.failed_rows', $failedRowsCount, [
                    'count' => Number::format($failedRowsCount, locale: app()->getLocale()),
                ]);
            }

            return $body;
        });
    }
}
