<?php

namespace Database\Seeders;

use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\PublicationSubscriber;
use App\Models\PublicationSubscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seed the publishers receiving the periodicals created by the migrations regularly, since some months ago: their
 * requests of each period are made as by the scheduler, and the past ones are delivered.
 */
class FakePublicationSubscriberSeeder extends Seeder
{
    /**
     * The copies of the first, second and third renewable publication received by each publisher, and since how many
     * months.
     *
     * @var list<array{0: list<int>, 1: int}>
     */
    public const SUBSCRIPTIONS = [
        [[1, 0, 1], 11],
        [[2, 0, 2], 9],
        [[0, 1, 1], 7],
        [[1, 0, 0], 5],
        [[0, 1, 0], 4],
        [[0, 0, 1], 3],
        [[1, 0, 1], 1],
    ];

    /**
     * The publisher (index) and the publication (position) of the subscription stopped two months ago.
     *
     * @var array{0: int, 1: int}
     */
    private const STOPPED = [2, 1];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publications = Publication::offeredRenewable()->take(3)->values();
        $users = User::query()->whereKeyNot(1)->orderByDesc('id')->limit(count(self::SUBSCRIPTIONS))->get()->values();

        foreach ($users as $index => $user) {
            [$quantities, $months] = self::SUBSCRIPTIONS[$index];
            $since = today()->subMonthsNoOverflow($months)->startOfMonth()->addDays($index);
            $subscriber = PublicationSubscriber::query()->firstOrCreate(['user_id' => $user->getKey()]);

            foreach ($publications as $position => $publication) {
                if (($quantities[$position] ?? 0) === 0 || $subscriber->subscriptions()->where('publication_id', $publication->getKey())->exists()) {
                    continue;
                }

                $subscription = $subscriber->subscriptions()
                    ->create(['publication_id' => $publication->getKey(), 'quantity' => $quantities[$position], 'started_at' => $since->toDateString()]);

                // A publisher who no longer receives the large print edition, since two months ago: it stays in the history
                if ($index === self::STOPPED[0] && $position === self::STOPPED[1]) {
                    $stoppedOn = today()->subMonthsNoOverflow(2)->startOfMonth()->addDays(10);
                    self::renewEveryMonth($subscription, $stoppedOn);
                    $subscription->stop($stoppedOn);

                    continue;
                }

                self::renewEveryMonth($subscription, today());
            }
        }

        // The requests of the past months are delivered, a few days after; the ones of this month are pending
        PublicationRequest::query()
            ->whereNotNull('subscription_id')
            ->whereDate('requested_at', '<', today()->startOfMonth()->toDateString())
            ->whereNull('delivered_at')
            ->each(fn (PublicationRequest $request) => $request->update([
                'delivered_at' => Carbon::parse($request->requested_at)->addDays(3 + $request->id % 5)->min(today())->toDateString(),
            ]));

    }

    /**
     * Renew the subscription as the daily job did every month since its start, until the given day: the renewal
     * requests only the current period, so the history of the past months is made one month at a time.
     */
    public static function renewEveryMonth(PublicationSubscription $subscription, CarbonInterface $until): void
    {
        for ($day = $subscription->started_at->copy(); $day->lessThanOrEqualTo($until); $day = $day->copy()->startOfMonth()->addMonthNoOverflow()) {
            $subscription->renew($day);
        }

        $subscription->renew($until);
    }
}
