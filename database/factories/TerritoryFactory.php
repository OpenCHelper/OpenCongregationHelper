<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Territory>
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
        return $this->state(fn(array $state) => [
            'image_path' => 'https://placehold.co/600x400?text=' . $state['name'],
        ]);
    }

    public function withMap(): self
    {
        return $this->state(function (array $attributes) {
            $center_coords = [41.9102088,12.3711913];
            $random_latitude  = $this->faker->latitude($center_coords[0] - 0.1, $center_coords[0] + 0.1);
            $random_longitude = $this->faker->longitude($center_coords[1] - 0.1, $center_coords[1] + 0.1);
    
            return [
                'geojson' => json_encode(array(
                    'type' => 'FeatureCollection',
                    'features' => array(
                        array(
                            'type' => 'Feature',
                            'properties' => array(
                                'name' => 'Territory',
                            ),
                            'geometry' => array(
                                'type' => 'Polygon',
                                'coordinates' => array(
                                    array(
                                        array(
                                            $this->faker->longitude($random_longitude - 0.1, $random_longitude + 0.1),
                                            $this->faker->latitude($random_latitude - 0.1, $random_latitude + 0.1),
                                        ),
                                        array(
                                            $this->faker->longitude($random_longitude - 0.1, $random_longitude + 0.1),
                                            $this->faker->latitude($random_latitude - 0.1, $random_latitude + 0.1),
                                        ),
                                        array(
                                            $this->faker->longitude($random_longitude - 0.1, $random_longitude + 0.1),
                                            $this->faker->latitude($random_latitude - 0.1, $random_latitude + 0.1),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                )),
            ];
        });
    }
}
