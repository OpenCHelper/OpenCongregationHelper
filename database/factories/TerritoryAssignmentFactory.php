<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TerritoryAssignment>
 */
class TerritoryAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $started_at = $this->faker->dateTimeBetween('-1 year', 'now');
        $random_max_months = $this->faker->numberBetween(1, 6);

        $ended_at = $this->faker->dateTimeBetween($started_at, "+$random_max_months months");

        return [
            'started_at' => $started_at,
            'ended_at' => $this->faker->randomElement([$ended_at, null]),
            'notes' => $this->faker->sentence(),
            'created_by' => 1,
        ];
    }
}
