<?php

use App\Enums\DepartmentKey;
use App\Enums\Periodical;
use App\Filament\Resources\PeriodicalSubscriptions\Pages\ManagePeriodicalSubscriptions;
use App\Filament\Resources\PeriodicalSubscriptions\Widgets\PeriodicalTotals;
use App\Models\Department;
use App\Models\PeriodicalSubscription;
use App\Models\User;
use Database\Seeders\FakePeriodicalSubscriptionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
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
});

test('a publisher can be added with the copies of each periodical', function () {
    $publisher = User::factory()->create();

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => $publisher->id,
            'quantities' => [
                Periodical::Watchtower->value => 2,
                Periodical::WatchtowerLargePrint->value => null,
                Periodical::MeetingWorkbook->value => 1,
            ],
            'notes' => 'Delivered at home',
        ])
        ->assertHasNoFormErrors();

    $subscription = PeriodicalSubscription::sole();

    expect($subscription->user->is($publisher))->toBeTrue()
        ->and($subscription->quantityOf(Periodical::Watchtower))->toBe(2)
        ->and($subscription->quantityOf(Periodical::WatchtowerLargePrint))->toBe(0)
        ->and($subscription->quantityOf(Periodical::MeetingWorkbook))->toBe(1)
        ->and($subscription->notes)->toBe('Delivered at home');
});

test('a publisher must receive at least one copy', function () {
    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => User::factory()->create()->id,
            'quantities' => array_fill_keys(array_column(Periodical::cases(), 'value'), 0),
        ])
        ->assertHasFormErrors(['quantities.'.Periodical::Watchtower->value]);

    expect(PeriodicalSubscription::exists())->toBeFalse();
});

test('a publisher is listed only once', function () {
    $subscription = PeriodicalSubscription::factory()->create();

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->callAction(TestAction::make('create'), [
            'user_id' => $subscription->user_id,
            'quantities' => [Periodical::Watchtower->value => 1],
        ])
        ->assertHasFormErrors(['user_id']);
});

test('the copies of a publisher can be changed', function () {
    $subscription = PeriodicalSubscription::factory()->receiving([Periodical::Watchtower->value => 1])->create();

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->callAction(TestAction::make('edit')->table($subscription), [
            'quantities' => [
                Periodical::Watchtower->value => 0,
                Periodical::WatchtowerLargePrint->value => 1,
                Periodical::MeetingWorkbook->value => 0,
            ],
        ])
        ->assertHasNoFormErrors();

    expect($subscription->fresh())
        ->quantityOf(Periodical::Watchtower)->toBe(0)
        ->quantityOf(Periodical::WatchtowerLargePrint)->toBe(1);
});

test('the table shows the copies of each periodical, their totals, and filters by periodical', function () {
    $both = PeriodicalSubscription::factory()->receiving([Periodical::Watchtower->value => 2, Periodical::MeetingWorkbook->value => 1])->create();
    $largePrint = PeriodicalSubscription::factory()->receiving([Periodical::WatchtowerLargePrint->value => 1])->create();

    Livewire::test(ManagePeriodicalSubscriptions::class)
        ->assertCanSeeTableRecords([$both, $largePrint])
        ->assertTableColumnStateSet('quantities.'.Periodical::Watchtower->value, 2, $both)
        ->assertTableColumnStateSet('quantities.'.Periodical::Watchtower->value, 0, $largePrint)
        ->assertTableColumnSummarySet('quantities.'.Periodical::Watchtower->value, 'total', 2)
        ->assertTableColumnSummarySet('quantities.'.Periodical::WatchtowerLargePrint->value, 'total', 1)
        ->filterTable('periodical', Periodical::WatchtowerLargePrint->value)
        ->assertCanSeeTableRecords([$largePrint])
        ->assertCanNotSeeTableRecords([$both]);
});

test('the totals count the copies and the publishers of each periodical', function () {
    PeriodicalSubscription::factory()->receiving([Periodical::Watchtower->value => 2, Periodical::MeetingWorkbook->value => 1])->create();
    PeriodicalSubscription::factory()->receiving([Periodical::Watchtower->value => 1])->create();

    expect(PeriodicalSubscription::totals(PeriodicalSubscription::all()))->toBe([
        Periodical::Watchtower->value => ['copies' => 3, 'publishers' => 2],
        Periodical::WatchtowerLargePrint->value => ['copies' => 0, 'publishers' => 0],
        Periodical::MeetingWorkbook->value => ['copies' => 1, 'publishers' => 1],
    ]);

    Livewire::test(PeriodicalTotals::class, ['tableClass' => ManagePeriodicalSubscriptions::class])
        ->assertOk()
        ->assertSee(Periodical::Watchtower->getLabel())
        ->assertSee(trans_choice('filament-resources.periodical_subscription.widget_publishers', 2, ['count' => 2]));
});

test('users outside the department without a role cannot open the list', function () {
    $this->actingAs(User::factory()->create());

    $this->get(ManagePeriodicalSubscriptions::getUrl())->assertForbidden();
});

test('the subscription is deleted with its publisher', function () {
    $subscription = PeriodicalSubscription::factory()->create();

    $subscription->user->delete();

    expect(PeriodicalSubscription::exists())->toBeFalse();
});

test('the fake seeder creates the publishers receiving periodicals', function () {
    User::factory()->count(8)->create();

    $this->seed(FakePeriodicalSubscriptionSeeder::class);
    $this->seed(FakePeriodicalSubscriptionSeeder::class);

    expect(PeriodicalSubscription::count())->toBe(7);

    $this->get(ManagePeriodicalSubscriptions::getUrl())->assertOk();
});
