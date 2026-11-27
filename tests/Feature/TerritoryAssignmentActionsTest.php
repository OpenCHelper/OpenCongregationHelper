<?php

use App\Filament\Resources\Campaigns\Pages\ManageCampaignAssignments;
use App\Filament\Resources\Territories\Pages\ListTerritories;
use App\Filament\Resources\Territories\Pages\ManageTerritoryAssignments as ManageTerritoryHistory;
use App\Filament\Resources\TerritoryAssignments\Pages\ManageTerritoryAssignments;
use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Filament\Resources\Users\Pages\ManageUserAssignments;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\TerritoryReturnedNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/**
 * @param  array<int, string>  $permissions
 */
function userWithPermissions(array $permissions): User
{
    $user = User::factory()->create(['can_login' => true]);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->assignee = User::factory()->create();
    $this->territory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();
});

test('users allowed to create assignments can assign a territory', function () {
    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->callAction(TestAction::make('create_assignment')->table($this->territory), [
            'assignee_id' => $this->assignee->id,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified(__('filament-resources.database_notifications.assignment_success_title'));

    expect($this->territory->currentAssignment()->first()?->assignee_id)->toBe($this->assignee->id);
});

test('assigning a territory to a terminated campaign is refused', function () {
    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    $terminatedCampaign = Campaign::factory()->create([
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->subDay(),
    ]);

    Livewire::test(ListTerritories::class)
        ->callAction(TestAction::make('create_assignment')->table($this->territory), [
            'assignee_id' => $this->assignee->id,
            'campaign_id' => $terminatedCampaign->id,
        ])
        ->assertHasFormErrors(['campaign_id']);

    expect($this->territory->assignments()->count())->toBe(0);
});

test('assigning territories in bulk to a terminated campaign is refused', function () {
    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    $terminatedCampaign = Campaign::factory()->create([
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->subDay(),
    ]);

    Livewire::test(ListTerritories::class)
        ->selectTableRecords([$this->territory])
        ->callAction(TestAction::make('create_assignment_bulk')->table()->bulk(), [
            'assignee_id' => $this->assignee->id,
            'campaign_id' => $terminatedCampaign->id,
        ])
        ->assertHasFormErrors(['campaign_id']);

    expect($this->territory->assignments()->count())->toBe(0);
});

test('a failure notification is shown when the assignment cannot be saved', function () {
    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    TerritoryAssignment::creating(function (): void {
        throw new RuntimeException('Simulated database failure');
    });

    Livewire::test(ListTerritories::class)
        ->callAction(TestAction::make('create_assignment')->table($this->territory), [
            'assignee_id' => $this->assignee->id,
        ])
        ->assertNotified(__('filament-resources.database_notifications.assignment_error_title'));

    expect($this->territory->assignments()->count())->toBe(0);
});

test('assignment actions are hidden for users without assignment permissions', function () {
    $assignedTerritory = Territory::factory()->for($this->territory->territoryZone, 'territoryZone')->create();
    TerritoryAssignment::factory()->create([
        'territory_id' => $assignedTerritory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:Territory']));

    Livewire::test(ListTerritories::class)
        ->assertActionHidden(TestAction::make('create_assignment')->table($this->territory))
        ->assertActionHidden(TestAction::make('cancel_assignment')->table($assignedTerritory))
        ->assertActionHidden(TestAction::make('create_assignment_bulk')->table()->bulk())
        ->assertActionHidden(TestAction::make('cancel_assignment_bulk')->table()->bulk());
});

test('users allowed to update assignments can return a territory', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Update:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->assertActionVisible(TestAction::make('cancel_assignment')->table($this->territory))
        ->callAction(TestAction::make('cancel_assignment')->table($this->territory))
        ->assertNotified(__('filament-resources.database_notifications.territory_returned_success_title'));

    expect($assignment->refresh()->ended_at)->not->toBeNull();
});

test('returning territories in bulk skips the assignments that start in the future', function () {
    // Saved before the start date was validated: it cannot be returned before it starts
    $futureAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->addMonth(),
        'ended_at' => null,
    ]);
    $otherTerritory = Territory::factory()->for($this->territory->territoryZone, 'territoryZone')->create();
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $otherTerritory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Update:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->selectTableRecords([$this->territory, $otherTerritory])
        ->callAction(TestAction::make('cancel_assignment_bulk')->table()->bulk())
        ->assertHasNoErrors();

    expect($futureAssignment->refresh()->ended_at)->toBeNull()
        ->and($assignment->refresh()->ended_at)->not->toBeNull();
});

test('users that can list assignments see everyone assignments', function () {
    $otherAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:TerritoryAssignment']));

    expect(TerritoryAssignmentResource::canViewAllAssignments())->toBeTrue()
        ->and(TerritoryAssignmentResource::getEloquentQuery()->pluck('id')->all())->toContain($otherAssignment->id);
});

test('users that cannot list assignments only see their own', function () {
    $user = userWithPermissions(['View:TerritoryAssignment']);

    // Past assignments of the same territory (only one assignment can be in progress at a time)
    $ownAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $user->id,
        'ended_at' => now()->subDays(10),
    ]);
    $otherAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => now()->subDay(),
    ]);

    $this->actingAs($user);

    expect(TerritoryAssignmentResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($ownAssignment->id)
        ->not->toContain($otherAssignment->id);
});

test('campaign assignments cannot be created for an already assigned territory', function () {
    $campaign = Campaign::factory()->create(['valid_until' => now()->addMonth()]);
    TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions([
        'View:Campaign', 'ViewAny:Campaign', 'Update:Campaign',
        'ViewAny:TerritoryAssignment', 'Create:TerritoryAssignment',
    ]));

    Livewire::test(ManageCampaignAssignments::class, ['record' => $campaign->id])
        ->callAction(TestAction::make('create')->table(), [
            'territory_id' => $this->territory->id,
            'assignee_id' => $this->assignee->id,
            'started_at' => now()->toDateString(),
        ])
        ->assertHasFormErrors(['territory_id']);

    expect($this->territory->assignments()->count())->toBe(1);
});

test('assignee options are sorted by first name, then last name', function () {
    User::factory()->create(['first_name' => 'Zeno', 'last_name' => 'Bianchi']);
    User::factory()->create(['first_name' => 'Annamarie', 'last_name' => 'Abbott']);
    User::factory()->create(['first_name' => 'Anna', 'last_name' => 'Verdi']);

    $names = array_values(User::getAssigneeOptions());

    // Not a natural sort of the full names, which ignores the spaces: `Annamarie Abbott` would come before `Anna Verdi`
    $expected = User::all()
        ->sort(fn (User $a, User $b): int => [mb_strtolower($a->first_name), mb_strtolower($a->last_name)]
            <=> [mb_strtolower($b->first_name), mb_strtolower($b->last_name)])
        ->map(fn (User $user): string => $user->full_name)
        ->values()
        ->all();

    expect($names)->toBe($expected)
        ->and(array_search('Anna Verdi', $names))->toBeLessThan(array_search('Annamarie Abbott', $names))
        ->and($names)->toContain('Zeno Bianchi');
});

/**
 * The last notification sent by the page (title, status and body as plain text).
 *
 * @return array{title: string, status: ?string, body: ?string}
 */
function lastSentNotification(): array
{
    // Read like `Notification::assertNotified()` does
    app(NotificationMailbox::class)->flush();
    $notifications = new Notifications;
    $notifications->mount();
    $notifications->pullNotifications();
    $notification = $notifications->notifications->last()?->toArray() ?? [];

    return [
        'title' => $notification['title'] ?? '',
        'status' => $notification['status'] ?? null,
        'body' => isset($notification['body']) ? strip_tags(str_replace('<br>', "\n", (string) $notification['body'])) : null,
    ];
}

test('assigning territories in bulk tells which ones were skipped because already assigned', function () {
    $this->territory->update(['name' => 'Busy one']);
    TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);
    $freeTerritories = Territory::factory()->count(2)->for($this->territory->territoryZone, 'territoryZone')->create();

    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->selectTableRecords([$this->territory, ...$freeTerritories])
        ->callAction(TestAction::make('create_assignment_bulk')->table()->bulk(), [
            'assignee_id' => $this->assignee->id,
        ])
        ->assertHasNoFormErrors();

    expect(lastSentNotification())->toBe([
        'title' => trans_choice('filament-resources.territory.bulk_assigned', 2),
        'status' => 'warning',
        'body' => __('filament-resources.territory.bulk_skipped_already_assigned', ['territories' => 'Busy one']),
    ])
        ->and($freeTerritories->every(fn (Territory $territory): bool => $territory->currentAssignment()->exists()))->toBeTrue();
});

test('assigning territories in bulk confirms when all of them were assigned', function () {
    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->selectTableRecords([$this->territory])
        ->callAction(TestAction::make('create_assignment_bulk')->table()->bulk(), [
            'assignee_id' => $this->assignee->id,
        ]);

    expect(lastSentNotification())->toBe([
        'title' => trans_choice('filament-resources.territory.bulk_assigned', 1),
        'status' => 'success',
        'body' => null,
    ]);
});

test('returning territories in bulk tells which ones were skipped and why', function () {
    $this->territory->update(['name' => 'Not out']);
    TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for($this->territory->territoryZone, 'territoryZone')->create(['name' => 'Future start'])->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->addMonth(),
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Update:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->selectTableRecords(Territory::all())
        ->callAction(TestAction::make('cancel_assignment_bulk')->table()->bulk());

    $notification = lastSentNotification();

    expect($notification['title'])->toBe(trans_choice('filament-resources.territory.bulk_returned', 0))
        ->and($notification['status'])->toBe('danger')
        ->and($notification['body'])->toContain(__('filament-resources.territory.bulk_skipped_not_assigned', ['territories' => 'Not out']))
        ->and($notification['body'])->toContain('Future start (');
});

test('a long list of skipped territories is shortened', function () {
    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Update:TerritoryAssignment']));
    $territories = Territory::factory()->count(11)->for($this->territory->territoryZone, 'territoryZone')
        ->sequence(fn ($sequence): array => ['name' => 'T'.($sequence->index + 1)])
        ->create();

    Livewire::test(ListTerritories::class)
        ->selectTableRecords($territories)
        ->callAction(TestAction::make('cancel_assignment_bulk')->table()->bulk());

    expect(lastSentNotification()['body'])->toBe(__('filament-resources.territory.bulk_skipped_not_assigned', [
        'territories' => __('filament-resources.territory.bulk_and_more', ['territories' => 'T1, T2, T3, T4, T5, T6, T7, T8, T9, T10', 'count' => 1]),
    ]));
});

test('the assignments list can return the territories in progress', function (string $page, Closure $record, array $permissions) {
    $this->campaign = Campaign::factory()->create([
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->addDays(30),
    ]);
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'campaign_id' => $this->campaign->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->subDays(5),
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions([...$permissions, 'ViewAny:TerritoryAssignment', 'Update:TerritoryAssignment']));

    Livewire::test($page, ($record = $record->call($this)) ? ['record' => $record->getKey()] : [])
        ->assertActionVisible(TestAction::make('return_assignment')->table($assignment))
        ->callAction(TestAction::make('return_assignment')->table($assignment))
        ->assertNotified(__('filament-resources.database_notifications.territory_returned_success_title'));

    expect($assignment->refresh()->ended_at)->not->toBeNull();
    Notification::assertSentTo($this->assignee, TerritoryReturnedNotification::class);
})->with([
    'assignments' => [ManageTerritoryAssignments::class, fn () => null, []],
    'territory history' => [ManageTerritoryHistory::class, fn () => $this->territory, ['View:Territory', 'ViewAny:Territory']],
    'user history' => [ManageUserAssignments::class, fn () => $this->assignee, ['View:User', 'ViewAny:User']],
    'campaign history' => [ManageCampaignAssignments::class, fn () => $this->campaign, ['View:Campaign', 'ViewAny:Campaign']],
]);

test('the assignments list offers to return only the assignments in progress', function () {
    $returnedAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => now()->subDays(5),
    ]);
    $deletedAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->subDays(4),
        'ended_at' => null,
    ]);
    $deletedAssignment->delete();

    $this->actingAs(userWithPermissions(['ViewAny:TerritoryAssignment', 'Update:TerritoryAssignment']));

    Livewire::test(ManageTerritoryAssignments::class)
        ->filterTable('ended_at', null)
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$returnedAssignment, $deletedAssignment])
        ->assertActionHidden(TestAction::make('return_assignment')->table($returnedAssignment))
        ->assertActionHidden(TestAction::make('return_assignment')->table($deletedAssignment));
});

test('the assignments list does not offer to return a territory to users not allowed to update assignments', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:TerritoryAssignment']));

    Livewire::test(ManageTerritoryAssignments::class)
        ->assertCanSeeTableRecords([$assignment])
        ->assertActionHidden(TestAction::make('return_assignment')->table($assignment));

    expect($assignment->refresh()->ended_at)->toBeNull();
});

test('the assignment page can return the territory', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->subDays(5),
        'ended_at' => null,
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Update:TerritoryAssignment']));

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $assignment->getKey()])
        ->assertActionVisible('return_assignment')
        ->callAction('return_assignment')
        ->assertNotified(__('filament-resources.database_notifications.territory_returned_success_title'))
        // Once returned, the territory cannot be returned again
        ->assertActionHidden('return_assignment');

    expect($assignment->refresh()->ended_at)->not->toBeNull();
    Notification::assertSentTo($this->assignee, TerritoryReturnedNotification::class);
});

test('the assignment page offers to return the territory only while in progress and to authorized users', function (array $permissions, ?Closure $endedAt, bool $isVisible) {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => $endedAt?->call($this),
    ]);

    $this->actingAs(userWithPermissions($permissions));

    $page = Livewire::test(ViewTerritoryAssignment::class, ['record' => $assignment->getKey()]);

    $isVisible
        ? $page->assertActionVisible('return_assignment')
        : $page->assertActionHidden('return_assignment');
})->with([
    'in progress, allowed' => [['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Update:TerritoryAssignment'], null, true],
    'in progress, not allowed' => [['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment'], null, false],
    'returned' => [['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Update:TerritoryAssignment'], fn () => now()->subDay(), false],
]);

test('the assignee cannot return their own territory without the permission to update assignments', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->actingAs($this->assignee);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $assignment->getKey()])
        ->assertActionHidden('return_assignment')
        ->assertActionVisible('requestReturn');
});

test('deleting an assignment explains that it leaves the history of the territory', function () {
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => now()->subDays(5),
    ]);

    $this->actingAs(userWithPermissions(['ViewAny:TerritoryAssignment', 'Delete:TerritoryAssignment', 'ForceDelete:TerritoryAssignment']));

    Livewire::test(ManageTerritoryAssignments::class)
        ->filterTable('ended_at', null)
        ->mountAction(TestAction::make('delete')->table($assignment))
        ->assertMountedActionModalSee(__('filament-resources.territory_assignment.delete_description'));

    $assignment->delete();

    Livewire::test(ManageTerritoryAssignments::class)
        ->filterTable('ended_at', null)
        ->filterTable('trashed', true)
        ->mountAction(TestAction::make('forceDelete')->table($assignment))
        ->assertMountedActionModalSee(__('filament-resources.territory_assignment.force_delete_description'));
});

test('the return is not offered for an assignment that starts in the future', function () {
    // Saved before the start date was validated: it cannot end before it starts
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);
    TerritoryAssignment::query()->whereKey($assignment->getKey())->update(['started_at' => now()->addDays(3)]);

    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Update:TerritoryAssignment']));

    Livewire::test(ManageTerritoryAssignments::class)
        ->assertCanSeeTableRecords([$assignment])
        ->assertActionHidden(TestAction::make('return_assignment')->table($assignment));

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $assignment->getKey()])
        ->assertActionHidden('return_assignment');

    Livewire::test(ListTerritories::class)
        ->assertActionHidden(TestAction::make('cancel_assignment')->table($this->territory));
});

test('the assignment page offers to return the territory only once when a banner offers it', function (Closure $state) {
    $campaign = Campaign::factory()->create([
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->addDays(60),
    ]);
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'campaign_id' => $campaign->id,
        'started_at' => now()->subDays(5),
        'ended_at' => null,
    ]);
    $state->call($this, $assignment, $campaign);

    $this->actingAs(userWithPermissions(['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Update:TerritoryAssignment']));

    expect(TerritoryAssignmentResource::hasReturnCallout($assignment->refresh()))->toBeTrue();

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $assignment->getKey()])
        ->assertActionHidden('return_assignment');
})->with([
    'campaign ended' => [fn (TerritoryAssignment $assignment, Campaign $campaign) => $campaign->update(['valid_until' => now()->subDay()])],
    'campaign ending' => [fn (TerritoryAssignment $assignment, Campaign $campaign) => $campaign->update(['valid_until' => now()->addDays(3)])],
    'return requested' => [fn (TerritoryAssignment $assignment) => $assignment->update(['return_requested_at' => now()])],
]);

test('deleting assignments in bulk explains that they leave the history of their territories', function (string $page, Closure $record, array $permissions) {
    $this->campaign = Campaign::factory()->create([
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->addDays(30),
    ]);
    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'campaign_id' => $this->campaign->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->subDays(10),
        'ended_at' => now()->subDays(5),
    ]);

    $this->actingAs(userWithPermissions([
        ...$permissions,
        'ViewAny:TerritoryAssignment', 'DeleteAny:TerritoryAssignment', 'ForceDeleteAny:TerritoryAssignment',
    ]));

    $parameters = ($record = $record->call($this)) ? ['record' => $record->getKey()] : [];

    Livewire::test($page, $parameters)
        ->filterTable('ended_at', null)
        ->selectTableRecords([$assignment])
        ->mountAction(TestAction::make('delete')->table()->bulk())
        ->assertMountedActionModalSee(__('filament-resources.territory_assignment.bulk_delete_description'));

    Livewire::test($page, $parameters)
        ->filterTable('ended_at', null)
        // Permanent deletion is only offered while the deleted records are shown
        ->filterTable('trashed', true)
        ->selectTableRecords([$assignment])
        ->mountAction(TestAction::make('forceDelete')->table()->bulk())
        ->assertMountedActionModalSee(__('filament-resources.territory_assignment.bulk_force_delete_description'));
})->with([
    'assignments' => [ManageTerritoryAssignments::class, fn () => null, []],
    'campaign history' => [ManageCampaignAssignments::class, fn () => $this->campaign, ['View:Campaign', 'ViewAny:Campaign']],
]);

/**
 * A campaign in progress, in which the territory of the test has already been worked (assigned and returned).
 */
function campaignWorkedBy(Territory $territory, User $assignee): Campaign
{
    $campaign = Campaign::factory()->create(['valid_from' => now()->subWeek(), 'valid_until' => now()->addMonth()]);
    TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => $assignee->id,
        'campaign_id' => $campaign->id,
        'started_at' => now()->subDays(5),
        'ended_at' => now()->subDay(),
    ]);

    return $campaign;
}

test('assigning a territory already worked in the campaign asks for a confirmation', function () {
    $campaign = campaignWorkedBy($this->territory, $this->assignee);
    $otherCampaign = Campaign::factory()->create(['valid_until' => now()->addMonth()]);

    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->mountAction(TestAction::make('create_assignment')->table($this->territory))
        ->fillForm(['assignee_id' => $this->assignee->id, 'campaign_id' => $otherCampaign->id])
        ->assertSchemaComponentHidden('confirm_campaign_repeat', 'mountedActionSchema0')
        ->fillForm(['campaign_id' => $campaign->id])
        ->assertSchemaComponentVisible('confirm_campaign_repeat', 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasFormErrors(['confirm_campaign_repeat' => 'accepted']);

    expect($this->territory->assignments()->count())->toBe(1);

    Livewire::test(ListTerritories::class)
        ->callAction(TestAction::make('create_assignment')->table($this->territory), [
            'assignee_id' => $this->assignee->id,
            'campaign_id' => $campaign->id,
            'confirm_campaign_repeat' => true,
        ])
        ->assertHasNoFormErrors();

    expect($this->territory->assignments()->where('campaign_id', $campaign->id)->count())->toBe(2);
});

test('assigning territories in bulk skips the ones already worked in the campaign unless asked', function () {
    $this->territory->update(['name' => 'Worked one']);
    $campaign = campaignWorkedBy($this->territory, $this->assignee);
    $freeTerritory = Territory::factory()->for($this->territory->territoryZone, 'territoryZone')->create();

    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->selectTableRecords([$this->territory, $freeTerritory])
        ->mountAction(TestAction::make('create_assignment_bulk')->table()->bulk())
        ->assertSchemaComponentHidden('include_worked_in_campaign', 'mountedActionSchema0')
        ->fillForm(['assignee_id' => $this->assignee->id, 'campaign_id' => $campaign->id])
        ->assertSchemaComponentVisible('include_worked_in_campaign', 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(lastSentNotification())->toBe([
        'title' => trans_choice('filament-resources.territory.bulk_assigned', 1),
        'status' => 'warning',
        'body' => __('filament-resources.territory.bulk_skipped_already_worked', ['territories' => 'Worked one']),
    ])
        ->and($this->territory->currentAssignment()->exists())->toBeFalse()
        ->and($freeTerritory->currentAssignment()->exists())->toBeTrue();
});

test('assigning territories in bulk can include the ones already worked in the campaign', function () {
    $campaign = campaignWorkedBy($this->territory, $this->assignee);

    $this->actingAs(userWithPermissions(['ViewAny:Territory', 'Create:TerritoryAssignment']));

    Livewire::test(ListTerritories::class)
        ->selectTableRecords([$this->territory])
        ->callAction(TestAction::make('create_assignment_bulk')->table()->bulk(), [
            'assignee_id' => $this->assignee->id,
            'campaign_id' => $campaign->id,
            'include_worked_in_campaign' => true,
        ])
        ->assertHasNoFormErrors();

    expect($this->territory->currentAssignment()->first()?->campaign_id)->toBe($campaign->id);
});

test('campaign assignments ask for a confirmation for a territory already worked in the campaign', function () {
    $campaign = campaignWorkedBy($this->territory, $this->assignee);

    $this->actingAs(userWithPermissions([
        'View:Campaign', 'ViewAny:Campaign', 'Update:Campaign',
        'ViewAny:TerritoryAssignment', 'Create:TerritoryAssignment',
    ]));

    $data = [
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'started_at' => now()->toDateString(),
    ];

    Livewire::test(ManageCampaignAssignments::class, ['record' => $campaign->id])
        ->callAction(TestAction::make('create')->table(), $data)
        ->assertHasFormErrors(['confirm_campaign_repeat' => 'accepted']);

    expect($this->territory->assignments()->count())->toBe(1);

    Livewire::test(ManageCampaignAssignments::class, ['record' => $campaign->id])
        ->callAction(TestAction::make('create')->table(), [...$data, 'confirm_campaign_repeat' => true])
        ->assertHasNoFormErrors();

    expect($this->territory->assignments()->count())->toBe(2);
});

test('only the returned assignments of the same campaign count as already worked', function () {
    $campaign = campaignWorkedBy($this->territory, $this->assignee);
    $otherTerritory = Territory::factory()->for($this->territory->territoryZone, 'territoryZone')->create();
    // In progress, not worked yet
    TerritoryAssignment::factory()->create([
        'territory_id' => $otherTerritory->id,
        'assignee_id' => $this->assignee->id,
        'campaign_id' => $campaign->id,
        'ended_at' => null,
    ]);

    expect(TerritoryAssignment::territoriesWorkedInCampaign([$this->territory->id, $otherTerritory->id], $campaign->id))
        ->toBe([$this->territory->id])
        ->and(TerritoryAssignment::territoryWorkedInCampaign($this->territory->id, null))->toBeFalse()
        ->and(TerritoryAssignment::territoryWorkedInCampaign($this->territory->id, Campaign::factory()->create()->id))->toBeFalse();
});
