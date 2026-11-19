<?php

namespace Database\Factories;

use App\Models\PeriodicalSubscription;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodicalSubscription>
 */
class PeriodicalSubscriptionFactory extends Factory
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
            'notes' => null,
        ];
    }

    /**
     * Receive the given copies of a publication.
     */
    public function receiving(Publication $publication, int $quantity = 1): self
    {
        return $this->afterCreating(fn (PeriodicalSubscription $subscription) => $subscription->items()->create([
            'publication_id' => $publication->getKey(),
            'quantity' => $quantity,
        ]));
    }
}
