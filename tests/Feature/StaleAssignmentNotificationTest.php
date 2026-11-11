<?php

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\StaleAssignmentNotification;
use Illuminate\Support\Facades\Notification;

test('stale assignment mail references the territory of the notified assignment', function () {
    Notification::fake();

    $user = User::factory()->create(['id' => 1]);
    $zone = TerritoryZone::factory()->create();

    $firstTerritory = Territory::factory()->for($zone, 'territoryZone')->create(['name' => 'First territory']);
    $secondTerritory = Territory::factory()->for($zone, 'territoryZone')->create(['name' => 'Second territory']);

    TerritoryAssignment::factory()->create([
        'territory_id' => $firstTerritory->id,
        'assignee_id' => $user->id,
        'started_at' => now()->subDays(100),
        'ended_at' => null,
    ]);
    $staleAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $secondTerritory->id,
        'assignee_id' => $user->id,
        'started_at' => now()->subDays(90),
        'ended_at' => null,
    ]);

    $mail = (new StaleAssignmentNotification($staleAssignment))->toMail($user);

    expect(implode(' ', $mail->introLines))
        ->toContain('Second territory')
        ->not->toContain('First territory');
});
