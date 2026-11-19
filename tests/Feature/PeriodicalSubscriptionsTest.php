<?php

use App\Enums\DepartmentKey;
use App\Enums\PublicationCategory;
use App\Filament\Resources\PeriodicalSubscriptions\Pages\ManagePeriodicalSubscriptions;
use App\Filament\Resources\PeriodicalSubscriptions\Widgets\PeriodicalTotals;
use App\Filament\Resources\Publications\Pages\ManagePublications;
use App\Models\Department;
use App\Models\PeriodicalSubscription;
use App\Models\Publication;
use App\Models\User;
use Database\Seeders\FakePeriodicalSubscriptionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // A member of the publications department, who can manage the list without a role
    $this->member = User::factory()->create();
    Department::for(DepartmentKey::Publications)->members()->attach($this->member);
    $this->actingAs($this->member);

    // The publications created by the migrations
    [$this->watchtower, $this->largePrint, $this->workbook] = Publication::query()->ordered()->get()->all();
});

/**
 * The copies of each publication, as in the form.
 *
 * @param  array<int, int>  $quantities  keyed by the ID of the publication
 * @return array<string, int>
 */
function formQuantities(array $quantities): array
{
    return collect($quantities)->mapWithKeys(fn (int $quantity, int $id): array => ["p{$id}" => $quantity])->all();
}

test('the publications are sorted by category, then as chosen in their list', function () {
    $book = Publication::factory()->create(['category' => PublicationCategory::Books, 'sort' => 0]);
    $bible = Publication::factory()->create(['category' => PublicationCategory::Bibles, 'sort' => 99]);

    expect(Publication::query()->ordered()->pluck('id')->all())
        ->toBe([$bible->id, $this->watchtower->id, $this->largePrint->id, $this->workbook->id, $book->id]);
});

test('the publications are grouped by category in their list and in the paper publications', function () {
    $book = Publication::factory()->create(['name' => 'Enjoy Life Forever!', 'category' => PublicationCategory::Books]);
    $subscription = PeriodicalSubscription::factory()->receiving($book, 2)->create();

    Livewire::test(ManagePublications::class)
        ->assertSee(PublicationCategory::Books->getLabel())
        ->filterTable('category', [PublicationCategory::Books->value])
        ->assertCanSeeTableRecords([$book])
        ->assertCanNotSeeTableRecords([$this->watchtower]);

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->assertSee(PublicationCategory::Books->getLabel())
        ->assertSee(PublicationCategory::Watchtower->getLabel())
        ->assertTableColumnStateSet("publication_{$book->id}", 2, $subscription);
});

test('the migrations create the usual publications, in the language of the installation', function () {
    expect(Publication::query()->ordered()->pluck('name')->all())->toBe([
        __('filament-resources.publication.defaults.watchtower.name', [], config('app.locale')),
        __('filament-resources.publication.defaults.watchtower_large_print.name', [], config('app.locale')),
        __('filament-resources.publication.defaults.meeting_workbook.name', [], config('app.locale')),
    ])->and(Publication::query()->ordered()->pluck('category')->all())->toBe([
        PublicationCategory::Watchtower,
        PublicationCategory::Watchtower,
        PublicationCategory::MeetingWorkbooks,
    ]);
});

test('a publisher can be added with the copies of each publication', function () {
    $publisher = User::factory()->create();

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => $publisher->id,
            'quantities' => [
                $this->watchtower->getFormKey() => 2,
                $this->largePrint->getFormKey() => null,
                $this->workbook->getFormKey() => 1,
            ],
            'notes' => 'Delivered at home',
        ])
        ->assertHasNoFormErrors();

    $subscription = PeriodicalSubscription::with('items')->sole();

    expect($subscription->user->is($publisher))->toBeTrue()
        ->and($subscription->quantityOf($this->watchtower))->toBe(2)
        ->and($subscription->quantityOf($this->largePrint))->toBe(0)
        ->and($subscription->quantityOf($this->workbook))->toBe(1)
        ->and($subscription->items)->toHaveCount(2)
        ->and($subscription->notes)->toBe('Delivered at home');
});

test('the publisher is chosen in a field named as in the rest of the application', function () {
    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->mountAction(TestAction::make('create'))
        ->assertFormFieldExists('user_id', fn (Select $field): bool => $field->getLabel() === __('filament-resources.periodical_subscription.field_user'));

    expect(__('filament-resources.periodical_subscription.field_user', [], 'it'))->toBe('Proclamatore');
});

test('a publisher must receive at least one copy', function () {
    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => User::factory()->create()->id,
            'quantities' => formQuantities([$this->watchtower->id => 0, $this->largePrint->id => 0, $this->workbook->id => 0]),
        ])
        ->assertHasFormErrors(['quantities.'.$this->watchtower->getFormKey()]);

    expect(PeriodicalSubscription::exists())->toBeFalse();
});

test('a publisher is listed only once', function () {
    $subscription = PeriodicalSubscription::factory()->receiving($this->watchtower)->create();

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => $subscription->user_id,
            'quantities' => formQuantities([$this->watchtower->id => 1]),
        ])
        ->assertHasFormErrors(['user_id']);
});

test('the copies of a publisher can be changed', function () {
    $subscription = PeriodicalSubscription::factory()->receiving($this->watchtower)->create();

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->mountAction(TestAction::make('edit')->table($subscription))
        ->assertSchemaStateSet(['quantities' => formQuantities([$this->watchtower->id => 1, $this->largePrint->id => 0, $this->workbook->id => 0])])
        ->fillForm(['quantities' => formQuantities([$this->watchtower->id => 0, $this->largePrint->id => 1, $this->workbook->id => 0])])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $subscription->load('items');

    expect($subscription->quantityOf($this->watchtower))->toBe(0)
        ->and($subscription->quantityOf($this->largePrint))->toBe(1)
        ->and($subscription->items)->toHaveCount(1);
});

test('the table shows the copies of each publication, their totals, and filters by publication', function () {
    $both = PeriodicalSubscription::factory()->receiving($this->watchtower, 2)->receiving($this->workbook)->create();
    $largePrint = PeriodicalSubscription::factory()->receiving($this->largePrint)->create();

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->assertCanSeeTableRecords([$both, $largePrint])
        ->assertTableColumnStateSet("publication_{$this->watchtower->id}", 2, $both)
        ->assertTableColumnStateSet("publication_{$this->watchtower->id}", 0, $largePrint)
        ->assertTableColumnSummarySet("publication_{$this->watchtower->id}", 'total', 2)
        ->assertTableColumnSummarySet("publication_{$this->largePrint->id}", 'total', 1)
        ->filterTable('publication', $this->largePrint->id)
        ->assertCanSeeTableRecords([$largePrint])
        ->assertCanNotSeeTableRecords([$both]);
});

test('a new publication can be received without changing the application', function () {
    Livewire::test(ManagePublications::class)
        ->callAction(TestAction::make('create'), [
            'name' => 'Awake!',
            'category' => PublicationCategory::Awake->value,
            'short_name' => 'Awake',
            'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    $awake = Publication::query()->where('name', 'Awake!')->sole();

    expect($awake->category)->toBe(PublicationCategory::Awake);
    $publisher = User::factory()->create();

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->assertTableColumnExists("publication_{$awake->id}")
        ->callAction(TestAction::make('create'), [
            'user_id' => $publisher->id,
            'quantities' => formQuantities([$awake->id => 3]),
        ])
        ->assertHasNoFormErrors();

    expect($publisher->periodicalSubscription()->sole()->load('items')->quantityOf($awake))->toBe(3);
});

test('a publication no longer offered is hidden, and the copies already entered are kept', function () {
    $subscription = PeriodicalSubscription::factory()->receiving($this->largePrint)->receiving($this->watchtower)->create();

    $this->largePrint->update(['is_active' => false]);

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->assertTableColumnDoesNotExist("publication_{$this->largePrint->id}")
        ->callAction(TestAction::make('edit')->table($subscription), [
            'quantities' => formQuantities([$this->watchtower->id => 2]),
        ])
        ->assertHasNoFormErrors();

    $subscription->load('items');

    expect($subscription->quantityOf($this->watchtower))->toBe(2)
        ->and($subscription->quantityOf($this->largePrint))->toBe(1);
});

test('nobody can be added while no publication is offered', function () {
    Publication::query()->update(['is_active' => false]);

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->assertActionDisabled('create');
});

test('the publications list counts the publishers and copies, and can be reordered', function () {
    PeriodicalSubscription::factory()->receiving($this->watchtower, 2)->create();
    PeriodicalSubscription::factory()->receiving($this->watchtower)->create();

    Livewire::test(ManagePublications::class)
        ->assertCanSeeTableRecords([$this->watchtower, $this->largePrint, $this->workbook], inOrder: true)
        ->assertTableColumnStateSet('subscription_items_count', 2, $this->watchtower)
        ->assertTableColumnStateSet('subscription_items_sum_quantity', 3, $this->watchtower)
        ->call('reorderTable', [$this->largePrint->getKey(), $this->watchtower->getKey(), $this->workbook->getKey()])
        ->assertHasNoErrors();

    expect(Publication::query()->ordered()->pluck('id')->all())
        ->toBe([$this->largePrint->id, $this->watchtower->id, $this->workbook->id]);
});

test('deleting a publication deletes its copies', function () {
    $subscription = PeriodicalSubscription::factory()->receiving($this->watchtower)->receiving($this->workbook)->create();

    Livewire::test(ManagePublications::class)
        ->callAction(TestAction::make('delete')->table($this->watchtower));

    expect($subscription->items()->pluck('publication_id')->all())->toBe([$this->workbook->id]);
});

test('the totals count the copies and the publishers of each publication', function () {
    PeriodicalSubscription::factory()->receiving($this->watchtower, 2)->receiving($this->workbook)->create();
    PeriodicalSubscription::factory()->receiving($this->watchtower)->create();

    expect(PeriodicalSubscription::totals(PeriodicalSubscription::query()->pluck('id')))->toEqual([
        $this->watchtower->id => ['copies' => 3, 'publishers' => 2],
        $this->workbook->id => ['copies' => 1, 'publishers' => 1],
    ]);

    Livewire::test(PeriodicalTotals::class, ['tableClass' => ManagePeriodicalSubscriptions::class])
        ->assertOk()
        ->assertSee($this->watchtower->name)
        ->assertSee(trans_choice('filament-resources.periodical_subscription.widget_publishers', 2, ['count' => 2]));
});

test('users outside the department without a role cannot open the lists', function () {
    $this->actingAs(User::factory()->create());

    $this->get(ManagePeriodicalSubscriptions::getUrl())->assertForbidden();
    $this->get(ManagePublications::getUrl())->assertForbidden();
});

test('the subscription is deleted with its publisher', function () {
    $subscription = PeriodicalSubscription::factory()->receiving($this->watchtower)->create();

    $subscription->user->delete();

    expect(PeriodicalSubscription::exists())->toBeFalse();
});

test('the fake seeder creates the publishers receiving publications', function () {
    User::factory()->count(8)->create();

    $this->seed(FakePeriodicalSubscriptionSeeder::class);
    $this->seed(FakePeriodicalSubscriptionSeeder::class);

    $totals = PeriodicalSubscription::totals(PeriodicalSubscription::query()->pluck('id'));
    $book = Publication::query()->where('category', PublicationCategory::Books)->sole();

    expect(PeriodicalSubscription::count())->toBe(7)
        ->and($totals[$this->watchtower->id]['copies'])->toBe(5)
        ->and($totals[$book->id])->toBe(['copies' => 3, 'publishers' => 2]);

    $this->get(ManagePeriodicalSubscriptions::getUrl())->assertOk();
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
