<?php

namespace Database\Seeders;

use App\Enums\Periodical;
use App\Models\PeriodicalSubscription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed the publishers receiving the periodicals on paper.
 */
class FakePeriodicalSubscriptionSeeder extends Seeder
{
    /**
     * The copies received by each publisher, in order.
     *
     * @var list<array<string, int>>
     */
    private const QUANTITIES = [
        [Periodical::Watchtower->value => 1, Periodical::MeetingWorkbook->value => 1],
        [Periodical::Watchtower->value => 2, Periodical::MeetingWorkbook->value => 2],
        [Periodical::WatchtowerLargePrint->value => 1, Periodical::MeetingWorkbook->value => 1],
        [Periodical::Watchtower->value => 1],
        [Periodical::WatchtowerLargePrint->value => 1],
        [Periodical::MeetingWorkbook->value => 1],
        [Periodical::Watchtower->value => 1, Periodical::MeetingWorkbook->value => 1],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()->whereKeyNot(1)->orderByDesc('id')->limit(count(self::QUANTITIES))->get();

        foreach ($users->values() as $index => $user) {
            PeriodicalSubscription::query()->updateOrCreate(
                ['user_id' => $user->getKey()],
                ['quantities' => array_merge(
                    array_fill_keys(array_map(fn (Periodical $periodical): string => $periodical->value, Periodical::cases()), 0),
                    self::QUANTITIES[$index],
                )],
            );
        }
    }
}
