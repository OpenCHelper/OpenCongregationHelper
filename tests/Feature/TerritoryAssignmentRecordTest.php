<?php

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Support\TerritoryAssignmentRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    User::factory()->create(['id' => 1]);

    $this->zone = TerritoryZone::factory()->create();
});

/**
 * Assign a territory to a new user between the given dates.
 */
function assignTerritory(Territory $territory, string $startedAt, ?string $endedAt, string $assignee = 'John Doe'): TerritoryAssignment
{
    [$firstName, $lastName] = explode(' ', $assignee);

    return TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => User::factory()->create(['first_name' => $firstName, 'last_name' => $lastName])->id,
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
    ]);
}

test('the service year starts in September and is named after the year in which it ends', function () {
    expect(TerritoryAssignmentRecord::serviceYearOf(Carbon::parse('2026-08-31')))->toBe(2026)
        ->and(TerritoryAssignmentRecord::serviceYearOf(Carbon::parse('2026-09-01')))->toBe(2027)
        ->and(TerritoryAssignmentRecord::serviceYearStart(2026)->toDateString())->toBe('2025-09-01')
        ->and(TerritoryAssignmentRecord::serviceYearEnd(2026)->toDateTimeString())->toBe('2026-08-31 23:59:59');
});

test('the rows list every territory by name with the assignments of the service year', function () {
    $territory10 = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => '10']);
    $territory2 = Territory::factory()->for($this->zone, 'territoryZone')->create(['name' => '2']);

    // Before the service year: only its date of completion is recorded
    assignTerritory($territory2, '2025-03-01', '2025-04-15');
    assignTerritory($territory2, '2025-05-01', '2025-06-20');
    // In progress when the service year starts
    assignTerritory($territory2, '2025-08-01', '2025-09-10', 'Mary Smith');
    assignTerritory($territory2, '2025-10-01', '2025-11-05', 'Paul Brown');
    // Returned after the end of the service year: in progress in its record
    assignTerritory($territory2, '2026-08-01', '2026-09-03', 'Anna White');
    // After the service year
    assignTerritory($territory2, '2026-09-10', null);

    $rows = TerritoryAssignmentRecord::rows(2026);

    expect($rows->pluck('territory')->all())->toBe(['2', '10']);

    $row = $rows->first();

    expect($row['last_completed_at']->toDateString())->toBe('2025-06-20')
        ->and(collect($row['assignments'])->map(fn (array $assignment): array => [
            $assignment['assignee'],
            $assignment['assigned_at']->toDateString(),
            $assignment['completed_at']?->toDateString(),
        ])->all())->toBe([
            ['Mary Smith', '2025-08-01', '2025-09-10'],
            ['Paul Brown', '2025-10-01', '2025-11-05'],
            ['Anna White', '2026-08-01', null],
        ])
        ->and($rows->last()['last_completed_at'])->toBeNull()
        ->and($rows->last()['assignments'])->toBe([]);
});
