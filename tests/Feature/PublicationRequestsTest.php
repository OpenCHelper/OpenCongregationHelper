<?php

use App\Enums\DepartmentKey;
use App\Enums\PublicationCategory;
use App\Enums\PublicationRequestStatus;
use App\Filament\Resources\PublicationRequests\Pages\ManagePublicationRequests;
use App\Filament\Resources\PublicationRequests\PublicationRequestResource;
use App\Filament\Resources\PublicationRequests\Widgets\PublicationRequestsChart;
use App\Filament\Resources\PublicationRequests\Widgets\PublicationRequestsOverview;
use App\Filament\Resources\Users\Pages\ManageUserPublicationRequests;
use App\Jobs\RenewPublicationSubscriptions;
use App\Models\Department;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\PublicationSubscriber;
use App\Models\PublicationSubscription;
use App\Models\User;
use Database\Seeders\FakePublicationRequestSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // A member of the publications department, who can manage the requests without a role
    $this->member = User::factory()->create();
    Department::for(DepartmentKey::Publications)->members()->attach($this->member);
    $this->actingAs($this->member);

    // The renewable publications created by the migrations (renewed every month, every month, every two months): the
    // others are deleted, so that the tests deal with these only (see `PublicationsTest` for all of them)
    Publication::query()->whereNotIn('code', ['w', 'wlp', 'mwb'])->delete();
    [$this->watchtower, $this->largePrint, $this->workbook] = Publication::query()->ordered()->get()->all();
    $this->book = Publication::factory()->create(['name' => 'Enjoy Life Forever!', 'category' => PublicationCategory::Books, 'renewal_months' => null]);
    $this->publisher = User::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);
});

/**
 * A subscription of the publisher to the given publication, started on the given day, without requests yet.
 */
function subscribeTo(User $publisher, Publication $publication, string $since, int $quantity = 1): PublicationSubscription
{
    return PublicationSubscriber::query()->firstOrCreate(['user_id' => $publisher->id])
        ->subscriptions()
        ->create(['publication_id' => $publication->id, 'quantity' => $quantity, 'started_at' => $since]);
}

/**
 * The days of the requests of the subscription.
 *
 * @return list<string>
 */
function requestDays(PublicationSubscription $subscription): array
{
    return $subscription->requests()->orderBy('requested_at')->pluck('requested_at')->map(fn ($date): string => substr((string) $date, 0, 10))->all();
}

describe('renewals', function () {
    test('a monthly publication is requested on its start, then on the 1st of every month', function () {
        $this->travelTo('2026-03-20');
        $subscription = subscribeTo($this->publisher, $this->watchtower, '2026-01-15', 2);

        $made = $subscription->renew();

        expect($made)->toHaveCount(3)
            ->and(requestDays($subscription))->toBe(['2026-01-15', '2026-02-01', '2026-03-01'])
            ->and($subscription->requests()->pluck('quantity')->unique()->all())->toBe([2])
            ->and($subscription->requests()->pluck('user_id')->unique()->all())->toBe([$this->publisher->id])
            ->and($subscription->getNextRequestDate()->toDateString())->toBe('2026-04-01');
    });

    test('a publication renewed every two months skips a month', function () {
        $this->travelTo('2026-05-02');
        $subscription = subscribeTo($this->publisher, $this->workbook, '2026-01-10');

        $subscription->renew();

        expect(requestDays($subscription))->toBe(['2026-01-10', '2026-03-01', '2026-05-01']);
    });

    test('renewing again makes nothing until the next period, and a missed day is caught up', function () {
        $this->travelTo('2026-03-20');
        $subscription = subscribeTo($this->publisher, $this->watchtower, '2026-03-20');
        $subscription->renew();

        expect($subscription->renew())->toBe([]);

        // The scheduler did not run on the 1st: the next run catches up
        $this->travelTo('2026-04-03');

        expect($subscription->renew())->toHaveCount(1)
            ->and(requestDays($subscription))->toBe(['2026-03-20', '2026-04-01']);
    });

    test('a stopped subscription, or a publication no longer renewable, is not requested again', function () {
        $this->travelTo('2026-03-20');
        $stopped = subscribeTo($this->publisher, $this->watchtower, '2026-03-01');
        $stopped->renew();
        $stopped->stop();

        $notRenewable = subscribeTo(User::factory()->create(), $this->largePrint, '2026-03-01');
        $this->largePrint->update(['renewal_months' => null]);

        $this->travelTo('2026-06-10');

        expect($stopped->fresh()->renew())->toBe([])
            ->and($notRenewable->fresh()->renew())->toBe([])
            ->and($stopped->requests()->count())->toBe(1);
    });

    test('the copies changed apply to the next requests, the past ones keep theirs', function () {
        $this->travelTo('2026-03-20');
        $subscription = subscribeTo($this->publisher, $this->watchtower, '2026-03-01');
        $subscription->renew();
        $subscription->update(['quantity' => 3]);

        $this->travelTo('2026-04-01');
        $subscription->renew();

        expect($subscription->requests()->orderBy('requested_at')->pluck('quantity')->all())->toBe([1, 3]);
    });

    test('the daily job renews every active subscription and is scheduled every day', function () {
        $this->travelTo('2026-03-20');
        $watchtower = subscribeTo($this->publisher, $this->watchtower, '2026-02-10');
        $workbook = subscribeTo(User::factory()->create(), $this->workbook, '2026-03-05');
        $stopped = tap(subscribeTo(User::factory()->create(), $this->largePrint, '2026-01-05'))->stop(today()->subMonths(3));

        (new RenewPublicationSubscriptions)->handle();
        (new RenewPublicationSubscriptions)->handle();

        expect(requestDays($watchtower))->toBe(['2026-02-10', '2026-03-01'])
            ->and(requestDays($workbook))->toBe(['2026-03-05'])
            ->and($stopped->requests()->count())->toBe(0);

        // The schedule is defined when the Artisan application starts
        Artisan::all();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->description, RenewPublicationSubscriptions::class));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 9 * * *')
            ->and($event->timezone)->toBe('Europe/Rome');
    });
});

describe('requests page', function () {
    test('a one-off request can be added', function () {
        Livewire::test(ManagePublicationRequests::class)
            ->callAction(TestAction::make('create'), [
                'user_id' => $this->publisher->id,
                'publication_id' => $this->book->id,
                'quantity' => 2,
                'requested_at' => today()->toDateString(),
                'notes' => 'For a Bible study',
            ])
            ->assertHasNoFormErrors();

        $request = PublicationRequest::sole();

        expect($request)
            ->user_id->toBe($this->publisher->id)
            ->publication_id->toBe($this->book->id)
            ->quantity->toBe(2)
            ->notes->toBe('For a Bible study')
            ->created_by->toBe($this->member->id)
            ->and($request->getStatus())->toBe(PublicationRequestStatus::Requested)
            ->and($request->isRenewal())->toBeFalse()
            ->and(PublicationSubscriber::exists())->toBeFalse();
    });

    test('a request of a renewable publication starts a subscription, unless asked not to', function () {
        Livewire::test(ManagePublicationRequests::class)
            ->callAction(TestAction::make('create'), [
                'user_id' => $this->publisher->id,
                'publication_id' => $this->watchtower->id,
                'quantity' => 2,
                'renews' => true,
                'requested_at' => today()->toDateString(),
            ])
            ->assertHasNoFormErrors();

        $subscription = PublicationSubscription::query()->sole();

        expect($subscription->subscriber->user_id)->toBe($this->publisher->id)
            ->and($subscription->quantity)->toBe(2)
            ->and(PublicationRequest::sole()->isRenewal())->toBeTrue();

        Livewire::test(ManagePublicationRequests::class)
            ->callAction(TestAction::make('create'), [
                'user_id' => User::factory()->create()->id,
                'publication_id' => $this->largePrint->id,
                'quantity' => 1,
                'renews' => false,
                'requested_at' => today()->toDateString(),
            ])
            ->assertHasNoFormErrors();

        expect(PublicationSubscription::count())->toBe(1)
            ->and(PublicationRequest::query()->whereNull('subscription_id')->sole()->publication_id)->toBe($this->largePrint->id);
    });

    test('the steps must be in order', function () {
        Livewire::test(ManagePublicationRequests::class)
            ->callAction(TestAction::make('create'), [
                'user_id' => $this->publisher->id,
                'publication_id' => $this->book->id,
                'quantity' => 1,
                'requested_at' => today()->toDateString(),
                'ordered_at' => today()->subDay()->toDateString(),
                'delivered_at' => today()->subDays(2)->toDateString(),
            ])
            ->assertHasFormErrors(['ordered_at', 'delivered_at']);
    });

    test('a request is marked as ordered, then delivered; delivering it can skip the order', function () {
        $toOrder = PublicationRequest::factory()->create(['publication_id' => $this->book->id, 'requested_at' => today()->subDays(5)->toDateString()]);
        $inStorage = PublicationRequest::factory()->create(['publication_id' => $this->book->id]);

        Livewire::test(ManagePublicationRequests::class)
            ->assertActionVisible(TestAction::make('markAsOrdered')->table($toOrder))
            ->callAction(TestAction::make('markAsOrdered')->table($toOrder), ['date' => today()->subDays(3)->toDateString()])
            ->callAction(TestAction::make('markAsDelivered')->table($inStorage), ['date' => today()->toDateString()]);

        expect($toOrder->fresh()->getStatus())->toBe(PublicationRequestStatus::Ordered)
            ->and($toOrder->fresh()->ordered_at->toDateString())->toBe(today()->subDays(3)->toDateString())
            ->and($inStorage->fresh()->getStatus())->toBe(PublicationRequestStatus::Delivered)
            ->and($inStorage->fresh()->ordered_at)->toBeNull();

        Livewire::test(ManagePublicationRequests::class, ['activeTab' => 'all'])
            ->assertActionHidden(TestAction::make('markAsOrdered')->table($toOrder->fresh()))
            ->assertActionHidden(TestAction::make('markAsDelivered')->table($inStorage->fresh()))
            ->callAction(TestAction::make('markAsDelivered')->table($toOrder->fresh()), ['date' => today()->toDateString()]);

        expect($toOrder->fresh()->getStatus())->toBe(PublicationRequestStatus::Delivered);
    });

    test('many requests are marked as ordered or delivered at once, keeping the steps in order', function () {
        $older = PublicationRequest::factory()->create(['requested_at' => today()->subDays(10)->toDateString()]);
        $newer = PublicationRequest::factory()->create(['requested_at' => today()->toDateString()]);

        Livewire::test(ManagePublicationRequests::class)
            ->selectTableRecords([$older, $newer])
            ->callAction(TestAction::make('markAsOrdered')->table()->bulk(), ['date' => today()->subDays(5)->toDateString()]);

        expect($older->fresh()->ordered_at->toDateString())->toBe(today()->subDays(5)->toDateString())
            // Not before it was requested
            ->and($newer->fresh()->ordered_at->toDateString())->toBe(today()->toDateString());

        Livewire::test(ManagePublicationRequests::class, ['activeTab' => 'ordered'])
            ->selectTableRecords([$older, $newer])
            ->callAction(TestAction::make('markAsDelivered')->table()->bulk(), ['date' => today()->toDateString()]);

        expect(PublicationRequest::query()->delivered()->count())->toBe(2);
    });

    test('the tabs show the requests of each step', function () {
        $requested = PublicationRequest::factory()->create();
        $ordered = PublicationRequest::factory()->ordered()->create();
        $delivered = PublicationRequest::factory()->delivered()->create();

        Livewire::test(ManagePublicationRequests::class)
            ->assertCanSeeTableRecords([$requested])
            ->assertCanNotSeeTableRecords([$ordered, $delivered])
            ->set('activeTab', 'ordered')
            ->assertCanSeeTableRecords([$ordered])
            ->assertCanNotSeeTableRecords([$requested, $delivered])
            ->set('activeTab', 'delivered')
            ->assertCanSeeTableRecords([$delivered])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$requested, $ordered, $delivered]);

        expect(PublicationRequestResource::getNavigationBadge())->toBe('1');
    });

    test('grouped by publication, the copies to order of each one are added up', function () {
        PublicationRequest::factory()->create(['publication_id' => $this->book->id, 'quantity' => 2]);
        PublicationRequest::factory()->create(['publication_id' => $this->book->id, 'quantity' => 3]);

        Livewire::test(ManagePublicationRequests::class)
            ->set('tableGrouping', 'publication_id')
            ->assertSee($this->book->name)
            ->assertTableColumnSummarySet('quantity', 'total', 5);
    });

    test('the requests can be filtered by publisher, publication, date and kind', function () {
        $book = PublicationRequest::factory()->create(['user_id' => $this->publisher->id, 'publication_id' => $this->book->id, 'requested_at' => '2026-01-10']);
        $renewal = subscribeTo($this->publisher, $this->watchtower, today()->toDateString());
        $renewal->renew();
        $other = PublicationRequest::factory()->create(['publication_id' => $this->book->id, 'requested_at' => today()->toDateString()]);
        $renewed = $renewal->requests()->sole();

        Livewire::test(ManagePublicationRequests::class, ['activeTab' => 'all'])
            ->filterTable('user', $this->publisher->id)
            ->assertCanSeeTableRecords([$book, $renewed])
            ->assertCanNotSeeTableRecords([$other])
            ->resetTableFilters()
            ->filterTable('publication', $this->book->id)
            ->assertCanSeeTableRecords([$book, $other])
            ->assertCanNotSeeTableRecords([$renewed])
            ->resetTableFilters()
            ->filterTable('requested_between', ['from' => '2026-01-01', 'until' => '2026-01-31'])
            ->assertCanSeeTableRecords([$book])
            ->assertCanNotSeeTableRecords([$other, $renewed])
            ->resetTableFilters()
            ->filterTable('renewal', true)
            ->assertCanSeeTableRecords([$renewed])
            ->assertCanNotSeeTableRecords([$book, $other]);
    });

    test('the links to the history of a publisher or a publication filter the requests', function () {
        expect(PublicationRequestResource::getFilteredUrl(user: 7))
            ->toContain('tab=all')
            ->toContain(urlencode('filters[user][value]').'=7');

        $book = PublicationRequest::factory()->delivered()->create(['user_id' => $this->publisher->id, 'publication_id' => $this->book->id]);
        $other = PublicationRequest::factory()->create();

        $this->get(PublicationRequestResource::getFilteredUrl(user: $this->publisher->id))->assertOk();

        Livewire::withQueryParams(['tab' => 'all', 'filters' => ['user' => ['value' => $this->publisher->id]]])
            ->test(ManagePublicationRequests::class)
            ->assertCanSeeTableRecords([$book])
            ->assertCanNotSeeTableRecords([$other]);
    });

    test('the widgets count the requests and draw the copies requested and delivered of the last 12 months', function () {
        $this->travelTo('2026-03-20');
        PublicationRequest::factory()->create(['publication_id' => $this->book->id, 'quantity' => 2, 'requested_at' => '2026-01-10', 'delivered_at' => '2026-02-05']);
        PublicationRequest::factory()->create(['publication_id' => $this->book->id, 'quantity' => 3, 'requested_at' => '2026-03-01', 'ordered_at' => '2026-03-02']);
        PublicationRequest::factory()->create(['publication_id' => $this->watchtower->id, 'quantity' => 1, 'requested_at' => '2026-03-15']);
        // Older than 12 months: not drawn
        PublicationRequest::factory()->create(['publication_id' => $this->book->id, 'quantity' => 9, 'requested_at' => '2025-01-15', 'delivered_at' => '2025-01-20']);

        $chart = Livewire::test(PublicationRequestsChart::class)->instance();
        $requested = $chart->getMonthlyCopies('requested_at');

        expect(array_keys($requested))->toHaveCount(12)
            ->and(array_key_first($requested))->toBe('2025-04')
            ->and(array_key_last($requested))->toBe('2026-03')
            ->and($requested['2026-01'])->toBe(2)
            ->and($requested['2026-03'])->toBe(4)
            ->and($chart->getMonthlyCopies('delivered_at')['2026-02'])->toBe(2);

        $chart->filter = (string) $this->book->id;

        expect($chart->getMonthlyCopies('requested_at')['2026-03'])->toBe(3);

        Livewire::test(PublicationRequestsOverview::class)
            ->assertOk()
            ->assertSee(trans_choice('filament-resources.publication_request.widget_copies', 1, ['count' => 1]))
            ->assertSee(trans_choice('filament-resources.publication_request.widget_copies', 3, ['count' => 3]));
    });
});

describe('access and history in context', function () {
    test('the user page has the history of the publications they requested', function () {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);

        $own = PublicationRequest::factory()->create(['user_id' => $this->publisher->id, 'publication_id' => $this->book->id]);
        $other = PublicationRequest::factory()->create();

        Livewire::test(ManageUserPublicationRequests::class, ['record' => $this->publisher->getRouteKey()])
            ->assertOk()
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableColumnHidden('user.full_name');

        $this->get(ManageUserPublicationRequests::getUrl(['record' => $this->publisher]))
            ->assertOk()
            ->assertSee(__('filament-pages.manage_user_publication_requests_title', ['name' => 'Mario Rossi']));
    });

    test('the user page of the requests is hidden from who cannot see the requests', function () {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('View:User', 'web'), Permission::findOrCreate('ViewAny:User', 'web'));
        $this->actingAs($viewer);

        expect(ManageUserPublicationRequests::canAccess(['record' => $this->publisher]))->toBeFalse();
    });

    test('only the members of the department, or with a role, can open the requests', function () {
        $this->get(ManagePublicationRequests::getUrl())->assertOk();

        $this->actingAs(User::factory()->create());

        $this->get(ManagePublicationRequests::getUrl())->assertForbidden();
    });

    test('the fake seeder creates one-off requests in every step', function () {
        User::factory()->count(7)->create();

        $this->seed(FakePublicationRequestSeeder::class);
        $this->seed(FakePublicationRequestSeeder::class);

        expect(PublicationRequest::count())->toBe(8)
            ->and(PublicationRequest::query()->toOrder()->exists())->toBeTrue()
            ->and(PublicationRequest::query()->ordered()->exists())->toBeTrue()
            ->and(PublicationRequest::query()->delivered()->exists())->toBeTrue()
            ->and(PublicationRequest::query()->whereColumn('delivered_at', '<', 'requested_at')->exists())->toBeFalse();
    });
});
