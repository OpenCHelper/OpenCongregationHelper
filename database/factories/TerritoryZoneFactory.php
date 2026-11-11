<?php

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\TerritoryZone;
use Database\Factories\Spatie\Tags\TagFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

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

    /**
     * Attach random tags of the `zones` type, creating one if none exists yet.
     */
    public function withTags(int $count = 1): self
    {
        return $this->afterCreating(function (TerritoryZone $zone) use ($count): void {
            for ($i = 0; $i < $count; $i++) {
                $zone->attachTag(TagFactory::randomOfType(TagType::ZONES));
            }
        });
    }
}
