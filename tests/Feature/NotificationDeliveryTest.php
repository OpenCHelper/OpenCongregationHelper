<?php

use App\Events\StaleAssignmentDetected;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\CampaignCreatedNotification;
use App\Notifications\StaleAssignmentNotification;
use App\Notifications\StaleAssignmentsDepartmentNotification;
use App\Notifications\TerritoryAssignedNotification;
use App\Notifications\TerritoryReturnedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

test('notifications are queued instead of being sent during the request', function (string $notification) {
    expect(is_subclass_of($notification, ShouldQueue::class))->toBeTrue();
})->with([
    CampaignCreatedNotification::class,
    StaleAssignmentNotification::class,
    StaleAssignmentsDepartmentNotification::class,
    TerritoryAssignedNotification::class,
    TerritoryReturnedNotification::class,
]);

test('new campaigns are notified only to users who can log in', function () {
    Notification::fake();

    User::factory()->create(['id' => 1]);
    $loginUser = User::factory()->create(['can_login' => true]);
    $virtualUser = User::factory()->create(['can_login' => false]);

    Campaign::factory()->create();

    Notification::assertSentTo($loginUser, CampaignCreatedNotification::class);
    Notification::assertNotSentTo($virtualUser, CampaignCreatedNotification::class);
});

/**
 * Create an assignment of a new territory to the given user.
 *
 * @param  array<string, mixed>  $attributes
 */
function assignNewTerritoryTo(User $assignee, array $attributes = []): TerritoryAssignment
{
    return TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => null,
        ...$attributes,
    ]);
}

test('a territory assigned in progress is notified to the assignee', function () {
    Notification::fake();

    $assignee = User::factory()->create(['id' => 1]);

    assignNewTerritoryTo($assignee);

    Notification::assertSentToTimes($assignee, TerritoryAssignedNotification::class, 1);
});

test('an assignment recorded as already returned notifies nothing', function () {
    Notification::fake();

    $assignee = User::factory()->create(['id' => 1]);

    assignNewTerritoryTo($assignee, ['ended_at' => now()->subDay()]);

    Notification::assertNothingSentTo($assignee);
});

test('the return of a territory is notified once, not when its date is corrected', function () {
    Notification::fake();

    $assignee = User::factory()->create(['id' => 1]);
    $assignment = assignNewTerritoryTo($assignee);

    $assignment->update(['ended_at' => now()->subDays(2)]);
    $assignment->update(['ended_at' => now()->subDay()]);

    Notification::assertSentToTimes($assignee, TerritoryReturnedNotification::class, 1);
});

test('assignments of virtual users notify nothing', function () {
    Notification::fake();

    User::factory()->create(['id' => 1]);
    $virtualUser = User::factory()->create(['can_login' => false]);

    $assignment = assignNewTerritoryTo($virtualUser, ['started_at' => now()->subDays(100)]);
    StaleAssignmentDetected::dispatch($assignment);
    $assignment->update(['ended_at' => now()]);

    Notification::assertNothingSentTo($virtualUser);
});

test('stale assignments are notified to assignees who can log in', function () {
    Notification::fake();

    $assignee = User::factory()->create(['id' => 1]);

    StaleAssignmentDetected::dispatch(assignNewTerritoryTo($assignee, ['started_at' => now()->subDays(100)]));

    Notification::assertSentTo($assignee, StaleAssignmentNotification::class);
});
