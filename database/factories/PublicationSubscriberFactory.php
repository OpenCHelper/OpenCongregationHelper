<?php

namespace Database\Factories;

use App\Models\Publication;
use App\Models\PublicationSubscriber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicationSubscriber>
 */
class PublicationSubscriberFactory extends Factory
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
     * Receive the given copies of a publication regularly, from today unless given (no request is made).
     */
    public function receiving(Publication $publication, int $quantity = 1, ?string $since = null): self
    {
        return $this->afterCreating(fn (PublicationSubscriber $subscriber) => $subscriber->subscriptions()->create([
            'publication_id' => $publication->getKey(),
            'quantity' => $quantity,
            'started_at' => $since ?? today()->toDateString(),
        ]));
    }
}
