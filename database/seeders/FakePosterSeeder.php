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
     * @var list<array{code: string, title: array<string, string>, hall: int, box: int}>
     */
    public const POSTERS = [
        ['code' => 'mp-01', 'title' => ['en' => 'Can the dead live again?', 'it' => 'I morti possono tornare a vivere?', 'es' => '¿Pueden volver a vivir los muertos?'], 'hall' => 2, 'box' => 3],
        ['code' => 'mp-02', 'title' => ['en' => 'Enjoy life forever!', 'it' => 'Vivi felice per sempre!', 'es' => '¡Disfrute de la vida para siempre!'], 'hall' => 3, 'box' => 5],
        ['code' => 'mp-03', 'title' => ['en' => 'How can you have a happy family?', 'it' => 'Come avere una famiglia felice?', 'es' => '¿Cómo tener una familia feliz?'], 'hall' => 1, 'box' => 2],
        ['code' => 'mp-04', 'title' => ['en' => 'What is God\'s Kingdom?', 'it' => 'Cos\'è il Regno di Dio?', 'es' => '¿Qué es el Reino de Dios?'], 'hall' => 2, 'box' => 0],
        ['code' => 'mp-05', 'title' => ['en' => 'Will suffering ever end?', 'it' => 'Le sofferenze finiranno?', 'es' => '¿Terminará algún día el sufrimiento?'], 'hall' => 0, 'box' => 4],
        ['code' => 'mp-06', 'title' => ['en' => 'Who controls the world?', 'it' => 'Chi controlla il mondo?', 'es' => '¿Quién controla el mundo?'], 'hall' => 1, 'box' => 1],
        ['code' => 'mp-07', 'title' => ['en' => 'Free Bible courses', 'it' => 'Corsi biblici gratuiti', 'es' => 'Cursos bíblicos gratuitos'], 'hall' => 4, 'box' => 6],
        ['code' => 'mp-08', 'title' => ['en' => 'Does God care about you?', 'it' => 'A Dio importa di te?', 'es' => '¿Le importa usted a Dios?'], 'hall' => 0, 'box' => 0],
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
