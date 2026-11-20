<?php

namespace Database\Seeders;

use App\Models\Poster;
use App\Models\StorageLocation;
use Illuminate\Database\Seeder;

/**
 * Seed some demo posters (obviously not real ones), kept in the Kingdom Hall and in a storage box, named in the
 * language of the installation.
 */
class FakePosterSeeder extends Seeder
{
    /**
     * The copies of each demo poster in the Kingdom Hall and in the storage box: none at all, only in one place, or in
     * both.
     *
     * @var list<array{hall: int, box: int}>
     */
    public const POSTERS = [
        ['hall' => 2, 'box' => 3],
        ['hall' => 3, 'box' => 5],
        ['hall' => 1, 'box' => 2],
        ['hall' => 2, 'box' => 0],
        ['hall' => 0, 'box' => 4],
        ['hall' => 1, 'box' => 1],
        ['hall' => 4, 'box' => 6],
        ['hall' => 0, 'box' => 0],
    ];

    /**
     * The title of the demo posters in every language, numbered with `:number`.
     *
     * @var array<string, string>
     */
    public const TITLES = [
        'en' => 'Demo poster :number',
        'it' => 'Poster di esempio :number',
        'es' => 'Póster de ejemplo :number',
    ];

    /**
     * The storage places in every language, the Kingdom Hall and the storage box (with its notes).
     *
     * @var array<string, array{hall: string, box: string, box_notes: string}>
     */
    public const STORAGE_PLACES = [
        'en' => ['hall' => 'Kingdom Hall', 'box' => 'Storage box', 'box_notes' => 'The key is in the literature room.'],
        'it' => ['hall' => 'Sala del Regno', 'box' => 'Box', 'box_notes' => 'La chiave è nella stanza delle pubblicazioni.'],
        'es' => ['hall' => 'Salón del Reino', 'box' => 'Trastero', 'box_notes' => 'La llave está en el cuarto de publicaciones.'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $places = self::getStoragePlaces();
        $hall = StorageLocation::query()->firstOrCreate(['name' => $places['hall']]);
        $box = StorageLocation::query()->firstOrCreate(['name' => $places['box']], ['notes' => $places['box_notes']]);

        foreach (self::POSTERS as $index => $data) {
            $number = $index + 1;
            $title = array_map(fn (string $title): string => str_replace(':number', (string) $number, $title), self::TITLES);
            $poster = Poster::query()->updateOrCreate(['code' => sprintf('demo-%02d', $number)], ['title' => $title]);

            foreach ([[$hall, $data['hall']], [$box, $data['box']]] as [$location, $quantity]) {
                $poster->stocks()->updateOrCreate(['storage_location_id' => $location->getKey()], ['quantity' => $quantity]);
            }
        }
    }

    /**
     * The names of the storage places in the language of the installation (in English when it has none).
     *
     * @return array{hall: string, box: string, box_notes: string}
     */
    public static function getStoragePlaces(): array
    {
        return self::STORAGE_PLACES[config('app.locale')] ?? self::STORAGE_PLACES['en'];
    }
}
