<?php

namespace Database\Seeders;

use App\Enums\PublicationCategory;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed the one-off requests of a book and of a tract, in every step (requested, ordered, delivered), over the last
 * months.
 */
class FakePublicationRequestSeeder extends Seeder
{
    /**
     * The requests: index of the publisher, publication (`book` or `tract`), copies, months ago, and whether they were
     * ordered and delivered.
     *
     * @var list<array{0: int, 1: string, 2: int, 3: int, 4: bool, 5: bool}>
     */
    private const REQUESTS = [
        [0, 'book', 1, 10, true, true],
        [1, 'book', 2, 8, false, true],
        [3, 'tract', 20, 6, true, true],
        [4, 'book', 1, 4, true, true],
        [5, 'tract', 50, 2, false, true],
        [2, 'book', 3, 1, true, false],
        [6, 'tract', 10, 0, false, false],
        [0, 'book', 1, 0, false, false],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publications = [
            'book' => Publication::query()->firstOrCreate(['code' => 'lff'], [
                'name' => ['en' => 'Enjoy Life Forever!', 'it' => 'Vivi felice per sempre!', 'es' => '¡Disfrute de la vida para siempre!'],
                'category' => PublicationCategory::Books,
            ]),
            'tract' => Publication::query()->firstOrCreate(['code' => 'T-36'], [
                'name' => ['en' => 'What Is the Kingdom of God?', 'it' => 'Cos\'è il Regno di Dio?', 'es' => '¿Qué es el Reino de Dios?'],
                'category' => PublicationCategory::Tracts,
            ]),
        ];

        if (PublicationRequest::query()->whereIn('publication_id', array_map(fn (Publication $publication) => $publication->getKey(), $publications))->exists()) {
            return;
        }

        $users = User::query()->whereKeyNot(1)->orderBy('id')->get()->values();

        foreach (self::REQUESTS as [$user, $publication, $quantity, $monthsAgo, $ordered, $delivered]) {
            $requestedAt = today()->subMonthsNoOverflow($monthsAgo)->startOfMonth()->addDays(9 + $user);

            if ($requestedAt->isFuture()) {
                $requestedAt = today();
            }

            PublicationRequest::query()->create([
                'user_id' => $users->get($user % max(1, $users->count()))->getKey(),
                'publication_id' => $publications[$publication]->getKey(),
                'quantity' => $quantity,
                'requested_at' => $requestedAt->toDateString(),
                'ordered_at' => $ordered ? $requestedAt->copy()->addDays(2)->min(today())->toDateString() : null,
                'delivered_at' => $delivered ? $requestedAt->copy()->addDays(9)->min(today())->toDateString() : null,
            ]);
        }
    }
}
