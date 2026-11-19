<?php

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Enums\PublicationCategory;
use App\Filament\Resources\PublicationRequests\PublicationRequestResource;
use App\Filament\Resources\Publications\Pages\ManagePublications;
use App\Filament\Resources\PublicationSubscribers\Pages\ManagePublicationSubscribers;
use App\Filament\Resources\PublicationSubscribers\PublicationSubscriberResource;
use App\Filament\Resources\PublicationSubscribers\Widgets\PublicationTotals;
use App\Models\Department;
use App\Models\Publication;
use App\Models\PublicationRequest;
use App\Models\PublicationSubscriber;
use App\Models\PublicationSubscription;
use App\Models\User;
use Database\Seeders\FakePublicationSubscriberSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    // Rows keyed by their position, to fill the repeater in the tests
    $this->undoRepeaterFake = Repeater::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // An overseer of the publications department, who can fully manage the list without a role
    $this->member = User::factory()->create();
    Department::for(DepartmentKey::Publications)->members()->attach($this->member, ['role' => DepartmentRole::Overseer]);
    $this->actingAs($this->member);

    // The renewable publications created by the migrations (renewed every month, every month, every two months): the
    // others are deleted, so that the tests deal with these only (see `PublicationsTest` for all of them)
    Publication::query()->whereNotIn('code', ['w', 'w-lp', 'mwb'])->delete();
    [$this->watchtower, $this->largePrint, $this->workbook] = Publication::query()->ordered()->get()->all();
});

afterEach(function () {
    ($this->undoRepeaterFake)();
});

/**
 * The rows of the copies received, as in the form.
 *
 * @param  array<int, int>  $quantities  keyed by the ID of the publication
 * @return list<array{publication_id: int, quantity: int}>
 */
function formItems(array $quantities): array
{
    return collect($quantities)
        ->map(fn (int $quantity, int $id): array => ['publication_id' => $id, 'quantity' => $quantity])
        ->values()
        ->all();
}

/**
 * Check the rows of the copies in the form, whatever their keys (`record-N` for the ones already saved).
 *
 * @param  array<int, int>  $quantities  keyed by the ID of the publication
 */
function expectItems(array $quantities): Closure
{
    return function (array $state) use ($quantities): array {
        expect(collect($state['activeSubscriptions'])->mapWithKeys(fn (array $item): array => [(int) $item['publication_id'] => (int) $item['quantity']])->all())
            ->toEqual($quantities);

        return [];
    };
}

test('the publications are sorted by category, then as chosen in their list', function () {
    $book = Publication::factory()->create(['category' => PublicationCategory::Books, 'sort' => 0]);
    $bible = Publication::factory()->create(['category' => PublicationCategory::Bibles, 'sort' => 99]);

    expect(Publication::query()->ordered()->pluck('id')->all())
        ->toBe([$bible->id, $this->watchtower->id, $this->largePrint->id, $this->workbook->id, $book->id]);
});

test('a publisher can be added with the publications received regularly, which are requested right away', function () {
    $publisher = User::factory()->create();

    Livewire::test(ManagePublicationSubscribers::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => $publisher->id,
            'activeSubscriptions' => formItems([$this->watchtower->id => 2, $this->workbook->id => 1]),
            'notes' => 'Delivered at home',
        ])
        ->assertHasNoFormErrors();

    $subscriber = PublicationSubscriber::query()->with('activeSubscriptions')->sole();

    expect($subscriber->user->is($publisher))->toBeTrue()
        ->and($subscriber->notes)->toBe('Delivered at home')
        ->and($subscriber->quantityOf($this->watchtower))->toBe(2)
        ->and($subscriber->quantityOf($this->largePrint))->toBe(0)
        ->and($subscriber->quantityOf($this->workbook))->toBe(1)
        ->and($subscriber->activeSubscriptions->pluck('started_at')->map->toDateString()->unique()->all())->toBe([today()->toDateString()])
        // The first request of each one is made right away
        ->and(PublicationRequest::query()->where('user_id', $publisher->id)->orderBy('publication_id')->get(['publication_id', 'quantity', 'requested_at'])->map(fn (PublicationRequest $request): array => [$request->publication_id, $request->quantity, $request->requested_at->toDateString()])->all())
        ->toBe([
            [$this->watchtower->id, 2, today()->toDateString()],
            [$this->workbook->id, 1, today()->toDateString()],
        ]);
});

test('the publisher is chosen in a field named as in the rest of the application', function () {
    Livewire::test(ManagePublicationSubscribers::class)
        ->mountAction(TestAction::make('create'))
        ->assertFormFieldExists('user_id', fn (Select $field): bool => $field->getLabel() === __('filament-resources.publication_subscriber.field_user'));

    expect(__('filament-resources.publication_subscriber.field_user', [], 'it'))->toBe('Proclamatore');
});

test('the form starts with no publications, added one row at a time', function () {
    Livewire::test(ManagePublicationSubscribers::class)
        ->mountAction(TestAction::make('create'))
        ->assertSchemaStateSet(['activeSubscriptions' => []]);
});

test('a publication can be chosen only once, with at least one copy', function () {
    Livewire::test(ManagePublicationSubscribers::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => User::factory()->create()->id,
            'activeSubscriptions' => [
                ['publication_id' => $this->watchtower->id, 'quantity' => 1],
                ['publication_id' => $this->watchtower->id, 'quantity' => 0],
            ],
        ])
        ->assertHasFormErrors(['activeSubscriptions.0.publication_id', 'activeSubscriptions.1.quantity']);

    expect(PublicationSubscriber::exists())->toBeFalse();
});

test('a publisher must receive at least one publication', function () {
    Livewire::test(ManagePublicationSubscribers::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => User::factory()->create()->id,
            'activeSubscriptions' => [],
        ])
        ->assertHasFormErrors(['activeSubscriptions']);

    expect(PublicationSubscriber::exists())->toBeFalse();
});

test('only the renewable publications can be received regularly', function () {
    $book = Publication::factory()->create(['renewal_months' => null]);

    $ids = collect(PublicationSubscriberResource::getPublicationOptions())->map(fn (array $group): array => array_keys($group))->flatten()->all();

    expect($ids)->toContain($this->watchtower->id, $this->workbook->id)
        ->not->toContain($book->id);
});

test('a publisher is listed only once', function () {
    $subscriber = PublicationSubscriber::factory()->receiving($this->watchtower)->create();

    Livewire::test(ManagePublicationSubscribers::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => $subscriber->user_id,
            'activeSubscriptions' => formItems([$this->watchtower->id => 1]),
        ])
        ->assertHasFormErrors(['user_id']);
});

test('changing the copies applies to the next requests, removing a publication stops it', function () {
    $subscriber = PublicationSubscriber::factory()->receiving($this->watchtower)->receiving($this->workbook)->create();
    $previous = PublicationRequest::factory()->create(['user_id' => $subscriber->user_id, 'publication_id' => $this->watchtower->id, 'quantity' => 1]);

    Livewire::test(ManagePublicationSubscribers::class)
        ->mountAction(TestAction::make('edit')->table($subscriber))
        ->assertSchemaStateSet(expectItems([$this->watchtower->id => 1, $this->workbook->id => 1]))
        ->fillForm(['activeSubscriptions' => formItems([$this->watchtower->id => 3])])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $subscriber->load('activeSubscriptions');

    expect($subscriber->quantityOf($this->watchtower))->toBe(3)
        ->and($subscriber->quantityOf($this->workbook))->toBe(0)
        // Stopped today, not deleted: it stays in the history
        ->and($subscriber->subscriptions()->where('publication_id', $this->workbook->id)->sole()->ended_at->toDateString())->toBe(today()->toDateString())
        ->and($previous->fresh()->quantity)->toBe(1);
});

test('only the publications removed from the rows are stopped, whatever the IDs of the subscriptions', function () {
    // Other subscriptions first, so that the IDs of the subscriptions differ from the IDs of their publications
    PublicationSubscriber::factory()->receiving($this->largePrint)->receiving($this->workbook)->create();
    $subscriber = PublicationSubscriber::factory()->receiving($this->watchtower)->receiving($this->workbook)->create();

    expect($subscriber->subscriptions()->where('publication_id', $this->watchtower->id)->value('id'))->not->toBe($this->watchtower->id);

    $subscriber->syncSubscriptions([['publication_id' => $this->watchtower->id, 'quantity' => 3]]);

    expect($subscriber->activeSubscriptions()->pluck('quantity', 'publication_id')->all())->toBe([$this->watchtower->id => 3]);
});

test('removing a publisher stops their subscriptions and keeps their history; adding them again reuses the record', function () {
    $subscriber = PublicationSubscriber::factory()->receiving($this->watchtower)->create();
    $request = PublicationRequest::factory()->create(['user_id' => $subscriber->user_id, 'publication_id' => $this->watchtower->id]);

    Livewire::test(ManagePublicationSubscribers::class)
        ->callAction(TestAction::make('remove')->table($subscriber))
        ->assertCanNotSeeTableRecords([$subscriber]);

    expect(PublicationSubscription::query()->active()->exists())->toBeFalse()
        ->and(PublicationSubscription::count())->toBe(1)
        ->and($request->fresh())->not->toBeNull();

    Livewire::test(ManagePublicationSubscribers::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => $subscriber->user_id,
            'activeSubscriptions' => formItems([$this->largePrint->id => 1]),
        ])
        ->assertHasNoFormErrors();

    expect(PublicationSubscriber::count())->toBe(1)
        ->and($subscriber->subscriptions()->count())->toBe(2)
        ->and($subscriber->activeSubscriptions()->sole()->publication_id)->toBe($this->largePrint->id);
});

test('the publishers can be removed in bulk', function () {
    $subscribers = PublicationSubscriber::factory()->count(2)->receiving($this->watchtower)->create();

    Livewire::test(ManagePublicationSubscribers::class)
        ->selectTableRecords($subscribers)
        ->callAction(TestAction::make('remove')->table()->bulk());

    expect(PublicationSubscription::query()->active()->exists())->toBeFalse()
        ->and(PublicationSubscription::count())->toBe(2);
});

test('the table shows the copies of each publication, their totals, and filters by publication', function () {
    $both = PublicationSubscriber::factory()->receiving($this->watchtower, 2)->receiving($this->workbook)->create();
    $largePrint = PublicationSubscriber::factory()->receiving($this->largePrint)->create();

    Livewire::test(ManagePublicationSubscribers::class)
        ->assertCanSeeTableRecords([$both, $largePrint])
        ->assertSee(PublicationCategory::Watchtower->getLabel())
        ->assertTableColumnStateSet("publication_{$this->watchtower->id}", 2, $both)
        ->assertTableColumnStateSet("publication_{$this->watchtower->id}", 0, $largePrint)
        ->assertTableColumnSummarySet("publication_{$this->watchtower->id}", 'total', 2)
        ->assertTableColumnSummarySet("publication_{$this->largePrint->id}", 'total', 1)
        ->filterTable('publication', $this->largePrint->id)
        ->assertCanSeeTableRecords([$largePrint])
        ->assertCanNotSeeTableRecords([$both]);
});

test('the stopped subscriptions are neither shown nor counted', function () {
    $subscriber = PublicationSubscriber::factory()->receiving($this->watchtower, 2)->receiving($this->workbook)->create();
    $subscriber->subscriptions()->where('publication_id', $this->watchtower->id)->sole()->stop();

    expect(PublicationSubscriber::totals([$subscriber->id]))->toEqual([
        $this->workbook->id => ['copies' => 1, 'publishers' => 1],
    ]);

    Livewire::test(ManagePublicationSubscribers::class)
        ->assertTableColumnStateSet("publication_{$this->watchtower->id}", 0, $subscriber);
});

test('a new renewable publication can be received without changing the application', function () {
    Livewire::test(ManagePublications::class)
        ->callAction(TestAction::make('create'), [
            'name' => [app()->getLocale() => 'Awake!'],
            'category' => PublicationCategory::Awake->value,
            'code' => 'g',
            'is_renewable' => true,
            'renewal_months' => 4,
            'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    $awake = Publication::query()->where('code', 'g')->sole();
    $publisher = User::factory()->create();

    expect($awake->category)->toBe(PublicationCategory::Awake)
        ->and($awake->renewal_months)->toBe(4);

    Livewire::test(ManagePublicationSubscribers::class)
        ->assertTableColumnExists("publication_{$awake->id}")
        ->callAction(TestAction::make('create'), [
            'user_id' => $publisher->id,
            'activeSubscriptions' => formItems([$awake->id => 3]),
        ])
        ->assertHasNoFormErrors();

    expect($publisher->publicationSubscriber()->sole()->load('activeSubscriptions')->quantityOf($awake))->toBe(3);
});

test('a publication no longer offered is hidden, and the copies already entered are kept', function () {
    $subscriber = PublicationSubscriber::factory()->receiving($this->largePrint)->receiving($this->watchtower)->create();

    $this->largePrint->update(['is_active' => false]);

    expect(collect(PublicationSubscriberResource::getPublicationOptions())->map(fn (array $group): array => array_keys($group))->flatten()->all())
        ->toContain($this->watchtower->id)
        ->not->toContain($this->largePrint->id)
        // The row already chosen keeps its publication, marked as no longer offered
        ->and(PublicationSubscriberResource::getPublicationOptions($this->largePrint->id)[PublicationCategory::Watchtower->getLabel()][$this->largePrint->id])
        ->toBe(__('filament-resources.publication_subscriber.publication_not_offered', ['name' => $this->largePrint->name]));

    Livewire::test(ManagePublicationSubscribers::class)
        ->assertTableColumnDoesNotExist("publication_{$this->largePrint->id}")
        ->mountAction(TestAction::make('edit')->table($subscriber))
        ->assertSchemaStateSet(expectItems([$this->largePrint->id => 1, $this->watchtower->id => 1]))
        ->fillForm(['activeSubscriptions' => formItems([$this->largePrint->id => 1, $this->watchtower->id => 2])])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $subscriber->load('activeSubscriptions');

    expect($subscriber->quantityOf($this->watchtower))->toBe(2)
        ->and($subscriber->quantityOf($this->largePrint))->toBe(1);
});

test('nobody can be added while no renewable publication is offered', function () {
    Publication::query()->update(['renewal_months' => null]);

    Livewire::test(ManagePublicationSubscribers::class)
        ->assertActionDisabled('create');
});

test('the publications list shows the renewal, counts the publishers, copies and pending requests, and can be reordered', function () {
    PublicationSubscriber::factory()->receiving($this->watchtower, 2)->create();
    PublicationSubscriber::factory()->receiving($this->watchtower)->create();
    PublicationRequest::factory()->create(['publication_id' => $this->watchtower->id]);
    PublicationRequest::factory()->delivered()->create(['publication_id' => $this->watchtower->id]);

    Livewire::test(ManagePublications::class)
        ->assertCanSeeTableRecords([$this->watchtower, $this->largePrint, $this->workbook], inOrder: true)
        ->assertTableColumnStateSet('renewal_months', 2, $this->workbook)
        ->assertTableColumnFormattedStateSet('renewal_months', trans_choice('filament-resources.publication.renewal_every', 2, ['count' => 2]), $this->workbook)
        ->assertTableColumnStateSet('active_subscriptions_count', 2, $this->watchtower)
        ->assertTableColumnStateSet('active_subscriptions_sum_quantity', 3, $this->watchtower)
        ->assertTableColumnStateSet('pending_requests_count', 1, $this->watchtower)
        ->call('reorderTable', [$this->largePrint->getKey(), $this->watchtower->getKey(), $this->workbook->getKey()])
        ->assertHasNoErrors();

    expect(Publication::query()->ordered()->pluck('id')->all())
        ->toBe([$this->largePrint->id, $this->watchtower->id, $this->workbook->id]);
});

test('the publications are grouped by category in their list', function () {
    $book = Publication::factory()->create(['name' => 'Enjoy Life Forever!', 'category' => PublicationCategory::Books]);

    Livewire::test(ManagePublications::class)
        ->assertSee(PublicationCategory::Books->getLabel())
        ->filterTable('category', [PublicationCategory::Books->value])
        ->assertCanSeeTableRecords([$book])
        ->assertCanNotSeeTableRecords([$this->watchtower]);
});

test('the publishers and the publications link to the history of their requests', function () {
    $subscriber = PublicationSubscriber::factory()->receiving($this->watchtower)->create();

    Livewire::test(ManagePublicationSubscribers::class)
        ->assertActionHasUrl(TestAction::make('requests')->table($subscriber), PublicationRequestResource::getFilteredUrl(user: $subscriber->user_id));

    Livewire::test(ManagePublications::class)
        ->assertActionHasUrl(TestAction::make('requests')->table($this->workbook), PublicationRequestResource::getFilteredUrl(publication: $this->workbook->id));
});

test('deleting a publication deletes its copies', function () {
    $subscriber = PublicationSubscriber::factory()->receiving($this->watchtower)->receiving($this->workbook)->create();

    Livewire::test(ManagePublications::class)
        ->callAction(TestAction::make('delete')->table($this->watchtower));

    expect($subscriber->subscriptions()->pluck('publication_id')->all())->toBe([$this->workbook->id]);
});

test('the totals count the copies and the publishers of each publication', function () {
    PublicationSubscriber::factory()->receiving($this->watchtower, 2)->receiving($this->workbook)->create();
    PublicationSubscriber::factory()->receiving($this->watchtower)->create();

    expect(PublicationSubscriber::totals(PublicationSubscriber::query()->pluck('id')))->toEqual([
        $this->watchtower->id => ['copies' => 3, 'publishers' => 2],
        $this->workbook->id => ['copies' => 1, 'publishers' => 1],
    ]);

    Livewire::test(PublicationTotals::class, ['tableClass' => ManagePublicationSubscribers::class])
        ->assertOk()
        ->assertSee($this->watchtower->name)
        ->assertSee(trans_choice('filament-resources.publication_subscriber.widget_publishers', 2, ['count' => 2]));
});

test('users outside the department without a role cannot open the lists', function () {
    $this->actingAs(User::factory()->create());

    $this->get(ManagePublicationSubscribers::getUrl())->assertForbidden();
    $this->get(ManagePublications::getUrl())->assertForbidden();
});

test('the subscriptions are deleted with their publisher', function () {
    $subscriber = PublicationSubscriber::factory()->receiving($this->watchtower)->create();

    $subscriber->user->delete();

    expect(PublicationSubscriber::exists())->toBeFalse()
        ->and(PublicationSubscription::exists())->toBeFalse();
});

test('the fake seeder creates the publishers receiving publications, with their history', function () {
    User::factory()->count(8)->create();

    $this->seed(FakePublicationSubscriberSeeder::class);
    $this->seed(FakePublicationSubscriberSeeder::class);

    $totals = PublicationSubscriber::totals(PublicationSubscriber::query()->pluck('id'));

    expect(PublicationSubscriber::count())->toBe(count(FakePublicationSubscriberSeeder::SUBSCRIPTIONS))
        ->and($totals[$this->watchtower->id]['copies'])->toBe(5)
        // One stopped two months ago, without requests after that
        ->and(PublicationSubscription::query()->whereNotNull('ended_at')->count())->toBe(1)
        ->and(PublicationSubscription::query()->whereNotNull('ended_at')->sole()->requests()->max('requested_at'))
        ->toBeLessThanOrEqual(PublicationSubscription::query()->whereNotNull('ended_at')->sole()->ended_at->toDateString())
        // The requests of the past months are delivered
        ->and(PublicationRequest::query()->whereDate('requested_at', '<', today()->startOfMonth())->whereNull('delivered_at')->exists())->toBeFalse()
        ->and(PublicationRequest::query()->whereNull('delivered_at')->exists())->toBeTrue();

    $this->get(ManagePublicationSubscribers::getUrl())->assertOk();
});

test('the publications are sorted with the table prefix of the installation', function () {
    $connection = Publication::query()->getConnection();
    $prefix = $connection->getTablePrefix();
    $connection->setTablePrefix('p45_');

    try {
        $sql = Publication::query()->ordered()->toSql();
    } finally {
        $connection->setTablePrefix($prefix);
    }

    expect($sql)->toContain($connection->getQueryGrammar()->wrap('p45_publications').'.');
});
