<?php

namespace Database\Seeders;

use App\Enums\TagType;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Faker\Generator as Faker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seed the zones and territories of a fictional congregation in the west of Rome, where the map is centered.
 *
 * The territories of each zone are laid out as blocks of a grid around the center of the zone, so that they
 * look like a real territory map instead of random overlapping shapes.
 */
class FakeTerritorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The number of territories in each row of the grid of a zone.
     */
    private const GRID_COLUMNS = 3;

    /**
     * The size of a territory block, in degrees of latitude and longitude (about 450 x 420 meters).
     */
    private const BLOCK_LATITUDE_SIZE = 0.004;

    private const BLOCK_LONGITUDE_SIZE = 0.005;

    /**
     * The zones to create, with their territories.
     *
     * @var list<array{name: string, description: string, color: string, center: array{float, float}, tags: list<string>, territories: list<array{name: string, description: string, tags: list<string>, notes?: string, is_commercial?: bool, with_image?: bool}>}>
     */
    public const ZONES = [
        [
            'name' => 'Casalotti',
            'description' => 'Residential district along Via di Casalotti, mostly small apartment buildings and terraced houses with private gardens.',
            'color' => '#2e7d32',
            'center' => [41.9200, 12.3500],
            'tags' => ['Residential', 'Suburban'],
            'territories' => [
                ['name' => 'Via di Casalotti (north)', 'description' => 'Via di Casalotti from the roundabout to Via Gasperina, both sides.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Via di Casalotti (south)', 'description' => 'Via di Casalotti from Via Gasperina to the end of the road, both sides.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Via Gasperina', 'description' => 'Via Gasperina and the side streets up to the park.', 'tags' => ['Single-family homes'], 'notes' => 'Many houses have dogs in the garden: ring at the gate and wait.'],
                ['name' => 'Via Santa Seconda', 'description' => 'Via Santa Seconda and Largo Millesimo.', 'tags' => ['Single-family homes', 'Gated'], 'notes' => 'The residential complex at number 40 has a caretaker: ask at the lodge in the morning.'],
                ['name' => 'Largo Casalotti shops', 'description' => 'Shops and offices around the square and the market.', 'tags' => ['Shops'], 'is_commercial' => true, 'notes' => 'Best on weekday mornings, avoid Saturdays.'],
                ['name' => 'Via Ventimiglia', 'description' => 'Via Ventimiglia and Via Ormea, up to the school.', 'tags' => ['Apartment buildings', 'Elderly residents'], 'with_image' => true],
            ],
        ],
        [
            'name' => 'Boccea',
            'description' => 'Dense area along Via di Boccea, with large apartment blocks and many shops at street level.',
            'color' => '#1565c0',
            'center' => [41.9150, 12.3800],
            'tags' => ['Residential', 'Mixed use'],
            'territories' => [
                ['name' => 'Via di Boccea (odd numbers)', 'description' => 'Via di Boccea, odd numbers from 1 to 299.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Via di Boccea (even numbers)', 'description' => 'Via di Boccea, even numbers from 2 to 300.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Via Lorenzo Litta', 'description' => 'Via Lorenzo Litta and Via Federico Borromeo.', 'tags' => ['Apartment buildings', 'Foreign language'], 'notes' => 'Several Spanish-speaking families: bring literature in Spanish.'],
                ['name' => 'Via Pietro Bembo', 'description' => 'Via Pietro Bembo from the church to the bus terminal.', 'tags' => ['Apartment buildings', 'Do not call list'], 'notes' => 'Check the do-not-call list in the card before starting.'],
                ['name' => 'Boccea market', 'description' => 'Covered market and the shops in the surrounding streets.', 'tags' => ['Shops'], 'is_commercial' => true],
                ['name' => 'Via Andrea Doria', 'description' => 'Via Andrea Doria and Piazza Irnerio.', 'tags' => ['Apartment buildings', 'Elderly residents'], 'with_image' => true],
            ],
        ],
        [
            'name' => 'Montespaccato',
            'description' => 'Hilly area with small villas and country roads; some streets have no sidewalks.',
            'color' => '#ef6c00',
            'center' => [41.8950, 12.3750],
            'tags' => ['Suburban', 'Low density'],
            'territories' => [
                ['name' => 'Via di Montespaccato', 'description' => 'Via di Montespaccato from the crossroad to the sports field.', 'tags' => ['Single-family homes', 'Car needed']],
                ['name' => 'Via Cornelia (west)', 'description' => 'Via Cornelia west of the railway bridge.', 'tags' => ['Single-family homes', 'Car needed'], 'notes' => 'Long distances between houses: a car is needed.'],
                ['name' => 'Via Cornelia (east)', 'description' => 'Via Cornelia east of the railway bridge, up to the Aurelia junction.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Via Domenico Tardini', 'description' => 'Via Domenico Tardini and the private roads off it.', 'tags' => ['Single-family homes', 'Gated']],
                ['name' => 'Montespaccato garden centres', 'description' => 'Garden centres, workshops and warehouses along the main road.', 'tags' => ['Shops', 'Car needed'], 'is_commercial' => true, 'with_image' => true],
            ],
        ],
        [
            'name' => 'Primavalle',
            'description' => 'Historic working-class neighbourhood with public housing blocks and busy shopping streets.',
            'color' => '#6a1b9a',
            'center' => [41.9250, 12.4100],
            'tags' => ['Residential', 'Mixed use'],
            'territories' => [
                ['name' => 'Via Mattia Battistini (north)', 'description' => 'Via Mattia Battistini from the metro station to Via di Torrevecchia.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Via Mattia Battistini (south)', 'description' => 'Via Mattia Battistini from Via di Torrevecchia to Piazza Capecelatro.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Via di Torrevecchia', 'description' => 'Via di Torrevecchia and the public housing blocks behind it.', 'tags' => ['Apartment buildings', 'Foreign language'], 'notes' => 'Many Romanian and Filipino families.'],
                ['name' => 'Piazza Capecelatro', 'description' => 'The square and the streets around the church.', 'tags' => ['Apartment buildings', 'Elderly residents']],
                ['name' => 'Battistini shops', 'description' => 'Shops along Via Mattia Battistini, from number 1 to 200.', 'tags' => ['Shops'], 'is_commercial' => true],
                ['name' => 'Via Federico Borromeo', 'description' => 'Via Federico Borromeo and Via Pasquale II.', 'tags' => ['Apartment buildings', 'Do not call list'], 'with_image' => true],
            ],
        ],
        [
            'name' => 'Pineta Sacchetti',
            'description' => 'Residential area between the park and the hospital, with condominiums and many gated buildings.',
            'color' => '#c62828',
            'center' => [41.9100, 12.4350],
            'tags' => ['Residential', 'Commercial'],
            'territories' => [
                ['name' => 'Via della Pineta Sacchetti (east)', 'description' => 'Via della Pineta Sacchetti from the hospital to the park entrance.', 'tags' => ['Apartment buildings', 'Gated'], 'notes' => 'Most buildings have a caretaker: ask politely to leave a letter.'],
                ['name' => 'Via della Pineta Sacchetti (west)', 'description' => 'Via della Pineta Sacchetti from the park entrance to Via di Boccea.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Via Cardinal Garampi', 'description' => 'Via Cardinal Garampi and Via Ormea.', 'tags' => ['Apartment buildings', 'Elderly residents']],
                ['name' => 'Hospital area offices', 'description' => 'Pharmacies, offices and cafés around the hospital.', 'tags' => ['Shops'], 'is_commercial' => true, 'notes' => 'Do not enter the hospital buildings.'],
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = app(Faker::class);
        $users = User::all();

        foreach (self::ZONES as $zoneData) {
            $zone = TerritoryZone::factory()->create([
                'name' => $zoneData['name'],
                'description' => $zoneData['description'],
                'color' => $zoneData['color'],
                'created_by' => $users->random()->id,
            ]);
            $zone->attachTags($zoneData['tags'], TagType::ZONES->value);

            foreach ($zoneData['territories'] as $index => $territoryData) {
                $territory = Territory::factory()->for($zone)->create([
                    'name' => $territoryData['name'],
                    'image_path' => ($territoryData['with_image'] ?? false)
                        ? 'https://placehold.co/600x400?text='.rawurlencode($territoryData['name'])
                        : null,
                    'description' => $territoryData['description'],
                    'notes' => $territoryData['notes'] ?? null,
                    'is_commercial' => $territoryData['is_commercial'] ?? false,
                    'geojson' => $this->blockGeoJson($faker, $zoneData['center'], $index, count($zoneData['territories']), $territoryData['name']),
                    'created_by' => $users->random()->id,
                ]);
                $territory->attachTags($territoryData['tags'], TagType::TERRITORIES->value);
            }
        }
    }

    /**
     * Build the GeoJSON of the block of the grid of a zone at the given position.
     *
     * The corners of the block are moved slightly inwards at random, so that the territories
     * do not look like a perfect grid and neighbouring ones never overlap.
     *
     * @param  array{float, float}  $center  The latitude and longitude of the center of the zone
     */
    private function blockGeoJson(Faker $faker, array $center, int $index, int $count, string $name): string
    {
        $rows = (int) ceil($count / self::GRID_COLUMNS);
        $row = intdiv($index, self::GRID_COLUMNS);
        $column = $index % self::GRID_COLUMNS;

        $south = $center[0] + ($row - $rows / 2) * self::BLOCK_LATITUDE_SIZE;
        $west = $center[1] + ($column - self::GRID_COLUMNS / 2) * self::BLOCK_LONGITUDE_SIZE;
        $north = $south + self::BLOCK_LATITUDE_SIZE;
        $east = $west + self::BLOCK_LONGITUDE_SIZE;

        $inset = fn (float $size): float => $faker->randomFloat(5, $size * 0.05, $size * 0.15);

        $corners = [
            [$west + $inset(self::BLOCK_LONGITUDE_SIZE), $south + $inset(self::BLOCK_LATITUDE_SIZE)],
            [$east - $inset(self::BLOCK_LONGITUDE_SIZE), $south + $inset(self::BLOCK_LATITUDE_SIZE)],
            [$east - $inset(self::BLOCK_LONGITUDE_SIZE), $north - $inset(self::BLOCK_LATITUDE_SIZE)],
            [$west + $inset(self::BLOCK_LONGITUDE_SIZE), $north - $inset(self::BLOCK_LATITUDE_SIZE)],
        ];

        return json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => ['name' => $name],
                    'geometry' => [
                        'type' => 'Polygon',
                        // A GeoJSON ring is closed: its last position is the first one
                        'coordinates' => [[...$corners, $corners[0]]],
                    ],
                ],
            ],
        ]);
    }
}
