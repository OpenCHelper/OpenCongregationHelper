<?php

use App\Events\StaleAssignmentDetected;
use App\Jobs\DetectStaleAssignments;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * The scheduled task of the stale assignments job, as defined in `bootstrap/app.php`.
 */
function staleAssignmentsScheduledTask(): ScheduledTask
{
    // The schedule is defined when the Artisan application starts
    Artisan::all();

    $tasks = collect(app(Schedule::class)->events())
        ->filter(fn (ScheduledTask $task): bool => $task->description === DetectStaleAssignments::class);

    expect($tasks)->toHaveCount(1);

    return $tasks->first();
}

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

test('the stale assignments job is scheduled every Monday at 9:00, Rome time', function () {
    $task = staleAssignmentsScheduledTask();

    expect($task->expression)->toBe('0 9 * * 1')
        ->and($task->timezone)->toBe('Europe/Rome')
        ->and($task->withoutOverlapping)->toBeTrue()
        ->and($task->onOneServer)->toBeTrue();
});

test('the stale assignments job is due on Monday at 9:00, Rome time', function (string $now, bool $isDue) {
    $task = staleAssignmentsScheduledTask();

    $this->travelTo(CarbonImmutable::parse($now));

    expect($task->isDue(app()))->toBe($isDue);
})->with([
    'Monday 9:00 in Rome, in winter' => ['2026-11-16 09:00 Europe/Rome', true],
    'Monday 9:00 in Rome, in summer' => ['2026-06-15 09:00 Europe/Rome', true],
    'Monday 0:00 UTC' => ['2026-11-16 00:00 UTC', false],
]);

test('the stale assignments job runs once a week when the scheduler is called as on the previews', function () {
    $task = staleAssignmentsScheduledTask();

    // As cron-job.org does for the previews (see `.github/workflows/deploy-preview.yml`): every 5 minutes, from 8:00 to
    // 22:55 (Rome time), Monday to Friday
    $monday = CarbonImmutable::parse('2026-11-16 00:00', 'Europe/Rome');
    $callsRunningTheTask = collect(range(0, 7 * 24 * 60 - 5, 5))
        ->map(fn (int $minutes): CarbonImmutable => $monday->addMinutes($minutes))
        ->filter(fn (CarbonImmutable $call): bool => $call->isWeekday() && $call->hour >= 8 && $call->hour <= 22)
        ->filter(function (CarbonImmutable $call) use ($task): bool {
            $this->travelTo($call);

            return $task->isDue(app());
        });

    expect($callsRunningTheTask)->toHaveCount(1);
});
