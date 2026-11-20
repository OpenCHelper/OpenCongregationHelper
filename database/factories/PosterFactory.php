<?php

namespace Database\Factories;

use App\Models\Poster;
use App\Models\StorageLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Poster>
 */
class PosterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('??-##'),
            'title' => rtrim($this->faker->sentence(4), '.'),
            'notes' => null,
        ];
    }

    /**
     * Keep the given copies of the poster in the given location.
     */
    public function inLocation(StorageLocation $location, int $quantity): self
    {
        return $this->afterCreating(fn (Poster $poster) => $poster->stocks()->create([
            'storage_location_id' => $location->getKey(),
            'quantity' => $quantity,
        ]));
    }
}
