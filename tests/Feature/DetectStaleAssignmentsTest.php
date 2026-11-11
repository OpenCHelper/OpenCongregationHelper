<?php

use App\Events\StaleAssignmentDetected;
use App\Jobs\DetectStaleAssignments;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

test('only assignments in progress for more than 60 days are reported as stale', function () {
    Notification::fake();

    $assignee = User::factory()->create(['id' => 1]);
    $zone = TerritoryZone::factory()->create();

    $createAssignment = fn (array $attributes): TerritoryAssignment => TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($zone, 'territoryZone')->create()->id,
        'assignee_id' => $assignee->id,
        ...$attributes,
    ]);

    $staleAssignment = $createAssignment(['started_at' => now()->subDays(61), 'ended_at' => null]);
    $veryStaleAssignment = $createAssignment(['started_at' => now()->subDays(200), 'ended_at' => null]);
    $createAssignment(['started_at' => now()->subDays(59), 'ended_at' => null]);
    $createAssignment(['started_at' => now()->subDays(200), 'ended_at' => now()->subDays(100)]);

    Event::fake([StaleAssignmentDetected::class]);

    (new DetectStaleAssignments)->handle();

    Event::assertDispatchedTimes(StaleAssignmentDetected::class, 2);
    Event::assertDispatched(StaleAssignmentDetected::class, fn (StaleAssignmentDetected $event): bool => $event->assignment->is($staleAssignment));
    Event::assertDispatched(StaleAssignmentDetected::class, fn (StaleAssignmentDetected $event): bool => $event->assignment->is($veryStaleAssignment));
});
