<?php

namespace Database\Seeders;

use App\Enums\PublicationCategory;
use App\Models\PeriodicalSubscription;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed the publishers receiving publications on paper: the periodicals created by the migrations, a book and a tract.
 */
class FakePeriodicalSubscriptionSeeder extends Seeder
{
    /**
     * The copies of the first, second and third publication received by each publisher, in order.
     *
     * @var list<list<int>>
     */
    private const QUANTITIES = [
        [1, 0, 1],
        [2, 0, 2],
        [0, 1, 1],
        [1, 0, 0],
        [0, 1, 0],
        [0, 0, 1],
        [1, 0, 1],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publications = Publication::query()->ordered()->limit(3)->get()->values();

        // A book and a tract too, besides the periodicals created by the migrations
        $book = Publication::query()->firstOrCreate(['name' => 'Enjoy Life Forever!'], ['category' => PublicationCategory::Books, 'short_name' => 'lff']);
        $tract = Publication::query()->firstOrCreate(['name' => 'What Is the Kingdom of God?'], ['category' => PublicationCategory::Tracts, 'short_name' => 'T-36']);
        $extras = [0 => [$book, 1], 2 => [$tract, 10], 5 => [$book, 2]];
        $users = User::query()->whereKeyNot(1)->orderByDesc('id')->limit(count(self::QUANTITIES))->get();

        foreach ($users->values() as $index => $user) {
            $quantities = [];

            foreach ($publications as $position => $publication) {
                $quantities[$publication->getKey()] = self::QUANTITIES[$index][$position] ?? 0;
            }

            if (isset($extras[$index])) {
                [$extra, $quantity] = $extras[$index];
                $quantities[$extra->getKey()] = $quantity;
            }

            $subscription = PeriodicalSubscription::query()->firstOrCreate(['user_id' => $user->getKey()]);

            foreach (array_filter($quantities) as $publicationId => $quantity) {
                $subscription->items()->updateOrCreate(['publication_id' => $publicationId], ['quantity' => $quantity]);
            }
        }
    }
}
