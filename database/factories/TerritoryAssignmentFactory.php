<?php

namespace Database\Factories;

use App\Models\TerritoryAssignment;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TerritoryAssignment>
 */
class TerritoryAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The dates depend on each other, so that an assignment never ends before it starts
     * nor in the future, even when one of the dates is given to the factory (an
     * assignment starting in the future is left in progress).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'started_at' => function (array $attributes): DateTimeInterface {
                $endedAt = Carbon::make($attributes['ended_at']);

                return $endedAt !== null
                    ? $this->faker->dateTimeBetween($endedAt->copy()->subMonths($this->faker->numberBetween(1, 6)), $endedAt)
                    : $this->faker->dateTimeBetween('-1 year', 'now');
            },
            'ended_at' => function (array $attributes): ?DateTimeInterface {
                $startedAt = Carbon::make($attributes['started_at']);

                if ($startedAt === null || $startedAt->isFuture() || $this->faker->boolean()) {
                    return null;
                }

                return $this->faker->dateTimeBetween($startedAt, 'now');
            },
            'notes' => $this->faker->sentence(),
            'created_by' => 1,
        ];
    }
}
