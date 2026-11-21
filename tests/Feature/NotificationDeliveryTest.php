<?php

use App\Models\Campaign;
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
