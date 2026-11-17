<?php

use App\Filament\Resources\TerritoryAssignments\Pages\ManageTerritoryAssignments;
use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/**
 * @param  array<int, string>  $permissions
 */
function actingAsOverdueAssignmentsUser(array $permissions): User
{
    $user = User::factory()->create(['can_login' => true]);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    test()->actingAs($user);

    return $user;
}

/**
 * Create an assignment in progress for the given campaign (if any).
 */
function assignmentInProgressFor(?Campaign $campaign): TerritoryAssignment
{
    return TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()->id,
        'assignee_id' => User::factory()->create()->id,
        'campaign_id' => $campaign?->id,
        'started_at' => now()->subMonth(),
        'ended_at' => null,
    ]);
}

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    // Assignments cannot be created for a terminated campaign: the campaign ends after they are created
    $this->endedCampaign = Campaign::factory()->create(['valid_from' => now()->subMonths(2), 'valid_until' => now()->addMonth()]);
    $this->endingCampaign = Campaign::factory()->create(['valid_from' => now()->subMonths(2), 'valid_until' => now()->addDays(3)]);
    $this->activeCampaign = Campaign::factory()->create(['valid_from' => now()->subMonths(2), 'valid_until' => now()->addMonth()]);

    $this->overdue = assignmentInProgressFor($this->endedCampaign);
    $this->dueSoon = assignmentInProgressFor($this->endingCampaign);
    $this->onTime = assignmentInProgressFor($this->activeCampaign);
    $this->withoutCampaign = assignmentInProgressFor(null);
    $this->returned = tap(assignmentInProgressFor($this->endedCampaign))->update(['ended_at' => now()->subDays(20)]);

    $this->endedCampaign->update(['valid_until' => now()->subDays(10)]);
});

test('only the assignments in progress of an ended campaign are overdue', function () {
    expect(TerritoryAssignment::query()->overdue()->pluck('id')->all())->toBe([$this->overdue->id])
        ->and($this->overdue->isOverdue())->toBeTrue()
        ->and($this->returned->isOverdue())->toBeFalse()
        ->and($this->dueSoon->isOverdue())->toBeFalse()
        ->and($this->withoutCampaign->isOverdue())->toBeFalse();
});

test('only the assignments in progress of a campaign ending within a week are due soon', function () {
    expect(TerritoryAssignment::query()->dueSoon()->pluck('id')->all())->toBe([$this->dueSoon->id])
        ->and($this->dueSoon->isDueSoon())->toBeTrue()
        ->and($this->overdue->isDueSoon())->toBeFalse()
        ->and($this->onTime->isDueSoon())->toBeFalse()
        ->and($this->withoutCampaign->isDueSoon())->toBeFalse();
});

test('the assignments of an ended or ending campaign stand out in the table', function () {
    actingAsOverdueAssignmentsUser(['ViewAny:TerritoryAssignment']);

    Livewire::test(ManageTerritoryAssignments::class)
        ->assertSeeHtml('fi-ta-row-assignment-overdue')
        ->assertSeeHtml('fi-ta-row-assignment-due-soon')
        ->assertSee(__('filament-resources.territory_assignment.campaign_ended', ['when' => $this->endedCampaign->valid_until->diffForHumans()]))
        ->assertSee(__('filament-resources.territory_assignment.campaign_ending', ['when' => $this->endingCampaign->valid_until->diffForHumans()]));
});

test('the tabs list the overdue and the due soon assignments', function () {
    actingAsOverdueAssignmentsUser(['ViewAny:TerritoryAssignment']);

    Livewire::test(ManageTerritoryAssignments::class)
        ->set('activeTab', 'overdue')
        ->assertCanSeeTableRecords([$this->overdue])
        ->assertCanNotSeeTableRecords([$this->dueSoon, $this->onTime, $this->withoutCampaign, $this->returned])
        ->set('activeTab', 'due_soon')
        ->assertCanSeeTableRecords([$this->dueSoon])
        ->assertCanNotSeeTableRecords([$this->overdue, $this->onTime, $this->withoutCampaign, $this->returned]);
});

test('a banner warns about the territories still out for an ended campaign', function () {
    actingAsOverdueAssignmentsUser(['ViewAny:TerritoryAssignment']);

    Livewire::test(ManageTerritoryAssignments::class)
        ->assertSee(trans_choice('filament-resources.territory_assignment.overdue_callout_heading', 1))
        ->callAction(TestAction::make('showOverdueAssignments')->schemaComponent('overdueAssignmentsCallout', 'content'))
        ->assertSet('activeTab', 'overdue');
});

test('the banner is not shown when no territory is out for an ended campaign', function () {
    $this->overdue->update(['ended_at' => now()]);

    actingAsOverdueAssignmentsUser(['ViewAny:TerritoryAssignment']);

    Livewire::test(ManageTerritoryAssignments::class)
        ->assertDontSee(trans_choice('filament-resources.territory_assignment.overdue_callout_heading', 1));
});

test('users allowed to update assignments can return all the overdue assignments', function () {
    actingAsOverdueAssignmentsUser(['ViewAny:TerritoryAssignment', 'Update:TerritoryAssignment']);

    Livewire::test(ManageTerritoryAssignments::class)
        ->callAction(TestAction::make('returnOverdueAssignments')->schemaComponent('overdueAssignmentsCallout', 'content'))
        ->assertNotified(trans_choice('filament-resources.territory_assignment.overdue_return_all_success', 1));

    expect($this->overdue->refresh()->ended_at)->not->toBeNull()
        ->and($this->dueSoon->refresh()->ended_at)->toBeNull()
        ->and($this->onTime->refresh()->ended_at)->toBeNull();
});

test('users not allowed to update assignments cannot return all the overdue assignments', function () {
    actingAsOverdueAssignmentsUser(['ViewAny:TerritoryAssignment']);

    Livewire::test(ManageTerritoryAssignments::class)
        ->assertActionHidden(TestAction::make('returnOverdueAssignments')->schemaComponent('overdueAssignmentsCallout', 'content'));

    expect($this->overdue->refresh()->ended_at)->toBeNull();
});

test('the page of an overdue assignment reminds its assignee to return it', function () {
    $assignee = actingAsOverdueAssignmentsUser(['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment', 'Update:TerritoryAssignment']);
    $this->overdue->update(['assignee_id' => $assignee->id]);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->overdue->getKey()])
        ->assertSee(__('filament-resources.territory_assignment.campaign_deadline_callout_description'))
        ->callAction(TestAction::make('returnAssignment')->schemaComponent('campaignDeadlineCallout', 'infolist'))
        ->assertNotified(__('filament-resources.territory_assignment.return_success'));

    expect($this->overdue->refresh()->ended_at)->not->toBeNull();
});

test('the page of an assignment on time does not show the reminder', function () {
    $assignee = actingAsOverdueAssignmentsUser(['ViewAny:TerritoryAssignment', 'View:TerritoryAssignment']);
    $this->onTime->update(['assignee_id' => $assignee->id]);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->onTime->getKey()])
        ->assertSuccessful()
        ->assertDontSee(__('filament-resources.territory_assignment.campaign_deadline_callout_description'));
});
