<?php

use App\Events\StaleAssignmentDetected;
use App\Jobs\DetectStaleAssignments;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Settings\TerritorySettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

test('stale assignments whose territory or zone was deleted are skipped', function () {
    Notification::fake();

    $assignee = User::factory()->create(['id' => 1]);

    $createStaleAssignment = fn (TerritoryZone $zone): TerritoryAssignment => TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($zone, 'territoryZone')->create()->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays(100),
        'ended_at' => null,
    ]);

    $zone = TerritoryZone::factory()->create();
    $staleAssignment = $createStaleAssignment($zone);

    // Deleted while still assigned, before deleting assigned territories (or zones with territories) was refused
    $createStaleAssignment($zone)->territory->deleteQuietly();
    $deletedZone = TerritoryZone::factory()->create();
    $createStaleAssignment($deletedZone);
    $deletedZone->deleteQuietly();

    Event::fake([StaleAssignmentDetected::class]);

    (new DetectStaleAssignments)->handle(app(TerritorySettings::class));

    Event::assertDispatchedTimes(StaleAssignmentDetected::class, 1);
    Event::assertDispatched(StaleAssignmentDetected::class, fn (StaleAssignmentDetected $event): bool => $event->assignment->is($staleAssignment));
});
