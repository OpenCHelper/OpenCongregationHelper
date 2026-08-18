<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Campaign '.$this->faker->word(),
            'description' => $this->faker->sentence(),
            'short_name' => $this->faker->regexify('[A-Z]{3}'),
            'created_by' => 1,
        ];
    }
}
