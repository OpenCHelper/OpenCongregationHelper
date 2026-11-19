<?php

namespace Database\Seeders;

use App\Models\Poster;
use App\Models\StorageLocation;
use Illuminate\Database\Seeder;

/**
 * Seed the magnetic posters of the displays, kept in the Kingdom Hall and in a storage box.
 */
class FakePosterSeeder extends Seeder
{
    /**
     * The posters, with their copies in the Kingdom Hall and in the storage box.
     *
     * @var list<array{code: string, title: string, hall: int, box: int}>
     */
    public const POSTERS = [
        ['code' => 'mp-01', 'title' => 'Can the dead live again?', 'hall' => 2, 'box' => 3],
        ['code' => 'mp-02', 'title' => 'Enjoy life forever!', 'hall' => 3, 'box' => 5],
        ['code' => 'mp-03', 'title' => 'How can you have a happy family?', 'hall' => 1, 'box' => 2],
        ['code' => 'mp-04', 'title' => 'What is God\'s Kingdom?', 'hall' => 2, 'box' => 0],
        ['code' => 'mp-05', 'title' => 'Will suffering ever end?', 'hall' => 0, 'box' => 4],
        ['code' => 'mp-06', 'title' => 'Who controls the world?', 'hall' => 1, 'box' => 1],
        ['code' => 'mp-07', 'title' => 'Free Bible courses', 'hall' => 4, 'box' => 6],
        ['code' => 'mp-08', 'title' => 'Does God care about you?', 'hall' => 0, 'box' => 0],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hall = StorageLocation::query()->firstOrCreate(['name' => 'Kingdom Hall']);
        $box = StorageLocation::query()->firstOrCreate(['name' => 'Storage box'], ['notes' => 'The key is in the literature room.']);

        foreach (self::POSTERS as $data) {
            $poster = Poster::query()->updateOrCreate(['code' => $data['code']], ['title' => $data['title']]);

            foreach ([[$hall, $data['hall']], [$box, $data['box']]] as [$location, $quantity]) {
                $poster->stocks()->updateOrCreate(['storage_location_id' => $location->getKey()], ['quantity' => $quantity]);
            }
        }
    }
}
