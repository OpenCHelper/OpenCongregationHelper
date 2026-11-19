<?php

namespace Database\Factories;

use App\Enums\PublicationCategory;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Publication>
 */
class PublicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => rtrim($this->faker->unique()->sentence(3), '.'),
            'category' => PublicationCategory::Books,
            'code' => null,
            'sort' => $this->faker->numberBetween(10, 99),
            'is_active' => true,
        ];
    }

    /**
     * A publication no longer offered to the publishers.
     */
    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
