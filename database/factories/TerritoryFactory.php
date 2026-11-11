<?php

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\Territory;
use Database\Factories\Spatie\Tags\TagFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Territory>
 */
class TerritoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->numerify('terr-###'),
            'description' => $this->faker->sentence(10),
            'notes' => $this->faker->sentence(),
            'is_commercial' => false,
        ];
    }

    public function isCommercial(): self
    {
        return $this->state([
            'is_commercial' => true,
        ]);
    }

    public function withImage(): self
    {
        return $this->state(fn (array $state) => [
            'image_path' => 'https://placehold.co/600x400?text='.$state['name'],
        ]);
    }

    public function withMap(): self
    {
        return $this->state(function (array $attributes) {
            $center_coords = [41.9102088, 12.3711913];
            $random_latitude = $this->faker->latitude($center_coords[0] - 0.1, $center_coords[0] + 0.1);
            $random_longitude = $this->faker->longitude($center_coords[1] - 0.1, $center_coords[1] + 0.1);

            return [
                'geojson' => json_encode([
                    'type' => 'FeatureCollection',
                    'features' => [
                        [
                            'type' => 'Feature',
                            'properties' => [
                                'name' => 'Territory',
                            ],
                            'geometry' => [
                                'type' => 'Polygon',
                                'coordinates' => [
                                    [
                                        [
                                            $this->faker->longitude($random_longitude - 0.1, $random_longitude + 0.1),
                                            $this->faker->latitude($random_latitude - 0.1, $random_latitude + 0.1),
                                        ],
                                        [
                                            $this->faker->longitude($random_longitude - 0.1, $random_longitude + 0.1),
                                            $this->faker->latitude($random_latitude - 0.1, $random_latitude + 0.1),
                                        ],
                                        [
                                            $this->faker->longitude($random_longitude - 0.1, $random_longitude + 0.1),
                                            $this->faker->latitude($random_latitude - 0.1, $random_latitude + 0.1),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ];
        });
    }

    /**
     * Attach random tags of the `territories` type, creating one if none exists yet.
     */
    public function withTags(int $count = 1): self
    {
        return $this->afterCreating(function (Territory $territory) use ($count): void {
            for ($i = 0; $i < $count; $i++) {
                $territory->attachTag(TagFactory::randomOfType(TagType::TERRITORIES));
            }
        });
    }
}
