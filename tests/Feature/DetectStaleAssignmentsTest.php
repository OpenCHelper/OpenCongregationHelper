<?php

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Events\StaleAssignmentDetected;
use App\Jobs\DetectStaleAssignments;
use App\Models\Department;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\StaleAssignmentNotification;
use App\Notifications\StaleAssignmentsDepartmentNotification;
use App\Settings\TerritorySettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

test('the stale assignments job is scheduled every Monday at 9:00, in the timezone of the application', function () {
    $task = staleAssignmentsScheduledTask();

    expect($task->expression)->toBe('0 9 * * 1')
        ->and($task->timezone)->toBe(config('app.timezone'))
        ->and($task->withoutOverlapping)->toBeTrue()
        ->and($task->onOneServer)->toBeTrue();
});

test('the stale assignments job is due on Monday at 9:00 in the timezone of the application', function (string $now, bool $isDue) {
    config(['app.timezone' => 'Europe/Rome']);

    $task = staleAssignmentsScheduledTask();

    $this->travelTo(CarbonImmutable::parse($now));

    expect($task->isDue(app()))->toBe($isDue);
})->with([
    'Monday 9:00 in Rome, in winter' => ['2026-11-16 09:00 Europe/Rome', true],
    'Monday 9:00 in Rome, in summer' => ['2026-06-15 09:00 Europe/Rome', true],
    'Monday 9:00 UTC' => ['2026-11-16 09:00 UTC', false],
    'Monday 0:00 UTC' => ['2026-11-16 00:00 UTC', false],
]);

test('the stale assignments job runs once a week when the scheduler is called as on the previews', function () {
    // The previews run in UTC, the default timezone
    config(['app.timezone' => 'UTC']);

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

test('the territory department receives one summary of the stale assignments, besides the assignees', function () {
    Notification::fake();

    $assignee = User::factory()->create(['id' => 1]);
    $zone = TerritoryZone::factory()->create();

    $overseer = User::factory()->create();
    $assistant = User::factory()->create();
    $virtualMember = User::factory()->create(['can_login' => false]);
    $otherDepartmentMember = User::factory()->create();

    Department::for(DepartmentKey::Territories)->members()->attach([
        $overseer->id => ['role' => DepartmentRole::Overseer],
        $assistant->id => ['role' => DepartmentRole::Assistant],
        $virtualMember->id => ['role' => DepartmentRole::Assistant],
    ]);
    Department::for(DepartmentKey::Publications)->members()->attach($otherDepartmentMember, ['role' => DepartmentRole::Overseer]);

    $staleAssignments = collect([61, 200])->map(fn (int $days): TerritoryAssignment => TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($zone, 'territoryZone')->create()->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays($days),
        'ended_at' => null,
    ]));
    TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($zone, 'territoryZone')->create()->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => null,
    ]);

    (new DetectStaleAssignments)->handle();

    Notification::assertSentToTimes($assignee, StaleAssignmentNotification::class, 2);

    foreach ([$overseer, $assistant] as $member) {
        Notification::assertSentToTimes($member, StaleAssignmentsDepartmentNotification::class, 1);
        Notification::assertSentTo(
            $member,
            StaleAssignmentsDepartmentNotification::class,
            fn (StaleAssignmentsDepartmentNotification $notification): bool => $notification->assignments->modelKeys() === $staleAssignments->pluck('id')->all()
                && $notification->staleAfterDays === 60,
        );
    }

    Notification::assertNotSentTo($virtualMember, StaleAssignmentsDepartmentNotification::class);
    Notification::assertNotSentTo($otherDepartmentMember, StaleAssignmentsDepartmentNotification::class);
    Notification::assertNotSentTo($assignee, StaleAssignmentsDepartmentNotification::class);
});

test('the territory department receives no summary without stale assignments', function () {
    Notification::fake();

    User::factory()->create(['id' => 1]);
    $member = User::factory()->create();
    Department::for(DepartmentKey::Territories)->members()->attach($member, ['role' => DepartmentRole::Overseer]);

    (new DetectStaleAssignments)->handle();

    Notification::assertNothingSentTo($member);
});

test('the summary of the territory department lists every stale assignment, the oldest first', function () {
    $member = User::factory()->create(['id' => 1, 'first_name' => 'Department', 'last_name' => 'Member']);
    $assignee = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $zone = TerritoryZone::factory()->create();

    $createAssignment = fn (string $territory, int $days): TerritoryAssignment => TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($zone, 'territoryZone')->create(['name' => $territory])->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays($days),
        'ended_at' => null,
    ]);

    $assignments = new EloquentCollection([$createAssignment('Recent territory', 70), $createAssignment('Old territory', 120)]);

    $mail = (new StaleAssignmentsDepartmentNotification($assignments, 60))->toMail($member);

    $lines = implode("\n", $mail->introLines);

    expect($mail->subject)->toBe(__('filament-resources.mailables.stale_assignments_department_subject', ['count' => 2]))
        ->and($lines)->toContain('60')
        ->toContain(__('filament-resources.mailables.stale_assignments_department_line', ['territory' => 'Old territory', 'assignee' => 'John Doe', 'days' => 120]))
        ->toContain(__('filament-resources.mailables.stale_assignments_department_line', ['territory' => 'Recent territory', 'assignee' => 'John Doe', 'days' => 70]))
        ->and(strpos($lines, 'Old territory'))->toBeLessThan(strpos($lines, 'Recent territory'));
});

test('only the overseers of the territory department receive the summary when chosen in the settings', function () {
    Notification::fake();

    $settings = app(TerritorySettings::class);
    $settings->stale_summary_overseers_only = true;
    $settings->save();

    $assignee = User::factory()->create(['id' => 1]);
    $overseer = User::factory()->create();
    $assistant = User::factory()->create();

    Department::for(DepartmentKey::Territories)->members()->attach([
        $overseer->id => ['role' => DepartmentRole::Overseer],
        $assistant->id => ['role' => DepartmentRole::Assistant],
    ]);

    TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory()->create(), 'territoryZone')->create()->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays(90),
        'ended_at' => null,
    ]);

    (new DetectStaleAssignments)->handle();

    Notification::assertSentToTimes($overseer, StaleAssignmentsDepartmentNotification::class, 1);
    Notification::assertNotSentTo($assistant, StaleAssignmentsDepartmentNotification::class);
});

test('the summary of the territory department is also shown in the app', function () {
    $member = User::factory()->create(['id' => 1]);
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory()->create(), 'territoryZone')->create()->id,
        'assignee_id' => $member->id,
        'started_at' => now()->subDays(90),
        'ended_at' => null,
    ]);

    $notification = new StaleAssignmentsDepartmentNotification(new EloquentCollection([$assignment]), 60);

    expect($notification->via($member))->toContain('mail', 'database');

    $member->notifyNow($notification, ['database']);

    expect($member->notifications()->where('type', StaleAssignmentsDepartmentNotification::class)->sole()->data)
        ->title->toBe(__('filament-resources.database_notifications.stale_assignments_department_title', ['count' => 1], $member->preferredLocale()))
        ->body->toBe(__('filament-resources.database_notifications.stale_assignments_department_body', ['days' => 60], $member->preferredLocale()));
});
