<?php

namespace Database\Factories;

use App\Enums\Periodical;
use App\Models\PeriodicalSubscription;
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
            'quantities' => [
                Periodical::Watchtower->value => 1,
                Periodical::WatchtowerLargePrint->value => 0,
                Periodical::MeetingWorkbook->value => 1,
            ],
            'notes' => null,
        ];
    }

    /**
     * Receive the given copies of each periodical (the others are not received).
     *
     * @param  array<string, int>  $quantities  keyed by `Periodical`
     */
    public function receiving(array $quantities): self
    {
        return $this->state(fn () => [
            'quantities' => array_merge(
                array_fill_keys(array_map(fn (Periodical $periodical): string => $periodical->value, Periodical::cases()), 0),
                $quantities,
            ),
        ]);
    }
}
