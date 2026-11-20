<?php

namespace Database\Factories;

use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicationRequest>
 */
class PublicationRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'publication_id' => Publication::factory(),
            'quantity' => $this->faker->numberBetween(1, 3),
            'requested_at' => today()->toDateString(),
            'notes' => null,
        ];
    }

    public function ordered(?string $on = null): self
    {
        return $this->state(fn () => ['ordered_at' => $on ?? today()->toDateString()]);
    }

    public function delivered(?string $on = null): self
    {
        return $this->state(fn () => ['delivered_at' => $on ?? today()->toDateString()]);
    }
}
