<?php

namespace Database\Seeders;

use App\Enums\TagType;
use App\Models\Tag;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Faker\Generator as Faker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seed the zones and territories of a fictional English-speaking congregation (around Springfield, Illinois).
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
            'name' => 'Northgate',
            'description' => 'Residential district north of the centre, mostly small apartment buildings and terraced houses with private gardens.',
            'color' => '#2e7d32',
            'center' => [39.8200, -89.6800],
            'tags' => ['Residential', 'Suburban'],
            'territories' => [
                ['name' => 'Maple Street (north)', 'description' => 'Maple Street from Northgate Road to Cedar Lane, both sides.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Maple Street (south)', 'description' => 'Maple Street from Cedar Lane to the railway, both sides.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Cedar Lane', 'description' => 'Cedar Lane and the side streets up to the park.', 'tags' => ['Single-family homes'], 'notes' => 'Many houses have dogs in the yard: ring at the gate and wait.'],
                ['name' => 'Willow Court', 'description' => 'Willow Court and Birch Place.', 'tags' => ['Single-family homes', 'Gated'], 'notes' => 'The gated community at the end of Willow Court has a gatehouse: ask the guard in the morning.'],
                ['name' => 'Northgate Plaza', 'description' => 'Shops and offices around the plaza and the supermarket.', 'tags' => ['Shops'], 'notes' => 'Best on weekday mornings, avoid Saturdays.', 'is_commercial' => true],
                ['name' => 'Juniper Avenue', 'description' => 'Juniper Avenue and Aspen Drive, up to the school.', 'tags' => ['Apartment buildings', 'Elderly residents'], 'with_image' => true],
            ],
        ],
        [
            'name' => 'Downtown',
            'description' => 'Dense area around Main Street, with large apartment blocks and many shops at street level.',
            'color' => '#1565c0',
            'center' => [39.8150, -89.6500],
            'tags' => ['Residential', 'Mixed use'],
            'territories' => [
                ['name' => 'Main Street (odd numbers)', 'description' => 'Main Street, odd numbers from 1 to 299.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Main Street (even numbers)', 'description' => 'Main Street, even numbers from 2 to 300.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Lincoln Avenue', 'description' => 'Lincoln Avenue and Jefferson Street.', 'tags' => ['Apartment buildings', 'Foreign language'], 'notes' => 'Several Spanish-speaking families: bring literature in Spanish.'],
                ['name' => 'Washington Street', 'description' => 'Washington Street from the church to the bus terminal.', 'tags' => ['Apartment buildings', 'Do not call list'], 'notes' => 'Check the do-not-call list in the card before starting.'],
                ['name' => 'Downtown market', 'description' => 'Market hall and the shops in the surrounding streets.', 'tags' => ['Shops'], 'is_commercial' => true],
                ['name' => 'Monroe Street', 'description' => 'Monroe Street and Adams Square.', 'tags' => ['Apartment buildings', 'Elderly residents'], 'with_image' => true],
            ],
        ],
        [
            'name' => 'Westwood',
            'description' => 'Hilly area with detached houses and country roads; some streets have no sidewalks.',
            'color' => '#ef6c00',
            'center' => [39.7950, -89.6550],
            'tags' => ['Suburban', 'Low density'],
            'territories' => [
                ['name' => 'Old Mill Road', 'description' => 'Old Mill Road from the crossroad to the sports field.', 'tags' => ['Single-family homes', 'Car needed']],
                ['name' => 'Country Club Road (west)', 'description' => 'Country Club Road west of the railway bridge.', 'tags' => ['Single-family homes', 'Car needed'], 'notes' => 'Long distances between houses: a car is needed.'],
                ['name' => 'Country Club Road (east)', 'description' => 'Country Club Road east of the railway bridge, up to the highway junction.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Fox Hollow Drive', 'description' => 'Fox Hollow Drive and the private roads off it.', 'tags' => ['Single-family homes', 'Gated']],
                ['name' => 'Westwood garden centers', 'description' => 'Garden centers, workshops and warehouses along Route 4.', 'tags' => ['Shops', 'Car needed'], 'is_commercial' => true, 'with_image' => true],
            ],
        ],
        [
            'name' => 'Riverside',
            'description' => 'Older neighbourhood along the river, with public housing blocks and busy shopping streets.',
            'color' => '#6a1b9a',
            'center' => [39.8250, -89.6200],
            'tags' => ['Residential', 'Mixed use'],
            'territories' => [
                ['name' => 'River Road (north)', 'description' => 'River Road from the train station to Grand Avenue.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'River Road (south)', 'description' => 'River Road from Grand Avenue to Harbor Square.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Grand Avenue', 'description' => 'Grand Avenue and the public housing blocks behind it.', 'tags' => ['Apartment buildings', 'Foreign language'], 'notes' => 'Many Polish and Filipino families.'],
                ['name' => 'Harbor Square', 'description' => 'The square and the streets around the church.', 'tags' => ['Apartment buildings', 'Elderly residents']],
                ['name' => 'River Road shops', 'description' => 'Shops along River Road, from number 1 to 200.', 'tags' => ['Shops'], 'is_commercial' => true],
                ['name' => 'Bridge Street', 'description' => 'Bridge Street and Pier Lane.', 'tags' => ['Apartment buildings', 'Do not call list'], 'with_image' => true],
            ],
        ],
        [
            'name' => 'Lakeview',
            'description' => 'Residential area between the lake park and the hospital, with condominiums and many gated buildings.',
            'color' => '#c62828',
            'center' => [39.8100, -89.5950],
            'tags' => ['Residential', 'Commercial'],
            'territories' => [
                ['name' => 'Lakeshore Drive (east)', 'description' => 'Lakeshore Drive from the hospital to the park entrance.', 'tags' => ['Apartment buildings', 'Gated'], 'notes' => 'Most buildings have a doorman: ask politely to leave a letter.'],
                ['name' => 'Lakeshore Drive (west)', 'description' => 'Lakeshore Drive from the park entrance to Main Street.', 'tags' => ['Apartment buildings', 'Intercom']],
                ['name' => 'Park Avenue', 'description' => 'Park Avenue and Elm Street.', 'tags' => ['Apartment buildings', 'Elderly residents']],
                ['name' => 'Medical center offices', 'description' => 'Pharmacies, offices and cafés around the hospital.', 'tags' => ['Shops'], 'notes' => 'Do not enter the hospital buildings.', 'is_commercial' => true],
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
            $zone->attachTags($this->tags($zoneData['tags'], TagType::ZONES));

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
                $territory->attachTags($this->tags($territoryData['tags'], TagType::TERRITORIES));
            }
        }
    }

    /**
     * Find or create the tags with the given names and type.
     *
     * @param  list<string>  $names
     * @return list<Tag>
     */
    private function tags(array $names, TagType $type): array
    {
        return array_map(fn (string $name): Tag => FakeTagSeeder::findOrCreate($name, $type), $names);
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
