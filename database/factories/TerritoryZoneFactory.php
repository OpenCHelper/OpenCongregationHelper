<?php

namespace Database\Factories;

use App\Models\TerritoryZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Tags\Tag;

/**
 * @extends Factory<TerritoryZone>
 */
class TerritoryZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->numerify('zone-###'),
            'description' => $this->faker->sentences(4, true),
        ];
    }

    public function withColor(): self
    {
        return $this->state(fn () => [
            'color' => $this->faker->hexColor(),
        ]);
    }

    public function withTags(int $count = 1): self
    {
        // Attach a random tag to the territory
        return $this->afterCreating(function ($territory) use ($count) {
            for ($i = 0; $i < $count; $i++) {
                $territory->attachTag(Tag::getWithType('zones')->random());
            }
        });
    }
}
