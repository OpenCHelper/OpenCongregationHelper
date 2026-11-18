<?php

namespace Database\Factories;

use App\Models\ServiceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceGroup>
 */
class ServiceGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->numerify('Group ##'),
        ];
    }

    /**
     * Create a coordinator and make them a member of the group.
     */
    public function withCoordinator(): self
    {
        return $this->afterCreating(function (ServiceGroup $serviceGroup): void {
            $coordinator = User::factory()->create(['service_group_id' => $serviceGroup->id]);

            $serviceGroup->coordinator()->associate($coordinator)->save();
        });
    }
}
