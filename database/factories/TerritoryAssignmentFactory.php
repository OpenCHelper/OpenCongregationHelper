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
        $date_start = $this->faker->dateTimeBetween('-1 year', 'now');
        $random_max_months = $this->faker->numberBetween(1, 6);

        $date_end = $this->faker->dateTimeBetween($date_start, "+$random_max_months months");

        return [
            'date_start' => $date_start,
            'date_end' => $this->faker->randomElement([$date_end, null]),
            'notes' => $this->faker->sentence(),
            'created_by' => 1,
        ];
    }
}
