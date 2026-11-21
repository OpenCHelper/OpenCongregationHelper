<?php

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Filament\Guest\Pages\ViewSharedAssignment;
use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\Department;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\TerritoryReturnRequestedNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->assignee = User::factory()->create(['can_login' => true, 'first_name' => 'Mario', 'last_name' => 'Rossi']);

    $this->overseer = User::factory()->create(['can_login' => true]);
    $this->assistant = User::factory()->create(['can_login' => true]);
    $department = Department::for(DepartmentKey::Territories);
    $department->members()->attach($this->overseer, ['role' => DepartmentRole::Overseer]);
    $department->members()->attach($this->assistant, ['role' => DepartmentRole::Assistant]);

    $this->territory = Territory::factory()
        ->for(TerritoryZone::factory(), 'territoryZone')
        ->create(['name' => 'Territory to return']);

    $this->assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $this->territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);
});

test('the assignee can ask the territory department to close the assignment', function () {
    $this->actingAs($this->assignee);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionVisible('requestReturn')
        ->callAction('requestReturn')
        ->assertNotified(__('filament-resources.territory_assignment.request_return_success_title'))
        ->assertActionHidden('requestReturn');

    $this->assignment->refresh();

    expect($this->assignment->return_requested_at)->not->toBeNull()
        ->and($this->assignment->ended_at)->toBeNull()
        ->and($this->assignment->isReturnRequested())->toBeTrue();

    Notification::assertSentTo([$this->overseer, $this->assistant], TerritoryReturnRequestedNotification::class);
    Notification::assertNotSentTo($this->assignee, TerritoryReturnRequestedNotification::class);
});

test('a publisher without permissions on the assignments opens their own assignment, but no one else\'s', function () {
    $this->withoutVite();

    $otherAssignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()->id,
        'assignee_id' => User::factory()->create()->id,
        'ended_at' => null,
    ]);

    $this->actingAs($this->assignee);

    $this->get(TerritoryAssignmentResource::getUrl('view', ['record' => $this->assignment]))
        ->assertOk()
        ->assertSee('Territory to return');
    // Not even found: the assignments of the others are left out of the query, so their existence is not revealed
    $this->get(TerritoryAssignmentResource::getUrl('view', ['record' => $otherAssignment]))
        ->assertNotFound();
    $this->get(TerritoryAssignmentResource::getUrl())
        ->assertForbidden();

    expect(TerritoryAssignmentResource::canViewAllAssignments())->toBeFalse()
        ->and(TerritoryAssignmentResource::getEloquentQuery()->pluck('id')->all())->toBe([$this->assignment->id]);
});

test('the policy lets only the assignee view an assignment without the permission', function () {
    $publisher = User::factory()->create(['can_login' => true]);

    expect($this->assignee->can('view', $this->assignment))->toBeTrue()
        ->and($publisher->can('view', $this->assignment))->toBeFalse();
});

test('only the members of the territory department are notified, not the one asking', function () {
    $publicationsMember = User::factory()->create();
    Department::for(DepartmentKey::Publications)->members()->attach($publicationsMember, ['role' => DepartmentRole::Overseer]);
    Department::for(DepartmentKey::Territories)->members()->attach($this->assignee, ['role' => DepartmentRole::Assistant]);

    $this->assignment->requestReturn($this->assignee);

    Notification::assertSentTo([$this->overseer, $this->assistant], TerritoryReturnRequestedNotification::class);
    Notification::assertNotSentTo([$publicationsMember, $this->assignee], TerritoryReturnRequestedNotification::class);
});

test('the return is asked only once', function () {
    expect($this->assignment->requestReturn($this->assignee))->toBeTrue()
        ->and(TerritoryAssignment::find($this->assignment->id)->requestReturn($this->assignee))->toBeFalse();

    Notification::assertSentToTimes($this->overseer, TerritoryReturnRequestedNotification::class, 1);
});

test('the return of a returned assignment cannot be asked', function () {
    $this->assignment->update(['ended_at' => now()]);

    expect($this->assignment->requestReturn($this->assignee))->toBeFalse()
        ->and($this->assignment->fresh()->return_requested_at)->toBeNull();

    $this->actingAs($this->assignee);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionHidden('requestReturn');

    Notification::assertNotSentTo($this->overseer, TerritoryReturnRequestedNotification::class);
});

test('only the assignee can ask to return the assignment', function (string $user) {
    $this->actingAs($this->{$user});

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionHidden('requestReturn');
})->with(['overseer', 'assistant']);

test('the super administrators, who can close the assignment, cannot ask to return it', function () {
    $admin = User::factory()->create(['can_login' => true]);
    $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    $this->actingAs($admin);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionHidden('requestReturn');
});

test('the territory department sees the request and closes the assignment in one click', function () {
    $this->assignment->requestReturn($this->assignee);
    $this->actingAs($this->assistant);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertSee(__('filament-resources.territory_assignment.return_requested_heading', [
            'date' => $this->assignment->return_requested_at->format('d/m/Y H:i'),
        ]))
        ->callAction(TestAction::make('returnRequestedAssignment')->schemaComponent('returnRequestedCallout', 'infolist'))
        ->assertNotified(__('filament-resources.territory_assignment.return_success'));

    expect($this->assignment->fresh()->ended_at)->not->toBeNull();
});

test('the assignee sees that the return has been asked, but cannot close the assignment', function () {
    $this->assignment->requestReturn($this->assignee);
    $this->actingAs($this->assignee);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertSee(__('filament-resources.territory_assignment.return_requested_heading', [
            'date' => $this->assignment->return_requested_at->format('d/m/Y H:i'),
        ]))
        ->assertActionHidden(TestAction::make('returnRequestedAssignment')->schemaComponent('returnRequestedCallout', 'infolist'));
});

test('the notification tells the department which territory to close and who has it', function () {
    $notification = new TerritoryReturnRequestedNotification($this->assignment);

    $mail = $notification->toMail($this->overseer);
    $database = $notification->toDatabase($this->overseer);

    expect($mail->subject)->toContain('Territory to return')
        ->and(implode(' ', $mail->introLines))->toContain('Territory to return')->toContain('Mario Rossi')
        ->and($database['title'])->toContain('Territory to return')
        ->and($database['body'])->toContain('Mario Rossi')
        ->and($database['actions'][0]['url'])->toContain('/territory-assignments/'.$this->assignment->id);
});

describe('from the shared link', function () {
    beforeEach(function () {
        Filament::setCurrentPanel('guest');

        $this->sharedUrl = $this->assignment->share();
    });

    test('the return cannot be asked unless enabled', function () {
        config(['app.guest_allow_returning_assignment' => false]);

        Livewire::test(ViewSharedAssignment::class, ['record' => $this->sharedUrl->uid])
            ->assertActionHidden('requestReturn');
    });

    test('anyone with the link can ask to return the assignment when enabled', function () {
        config(['app.guest_allow_returning_assignment' => true]);

        Livewire::test(ViewSharedAssignment::class, ['record' => $this->sharedUrl->uid])
            ->assertActionVisible('requestReturn')
            ->callAction('requestReturn')
            ->assertNotified(__('filament-resources.territory_assignment.request_return_success_title'))
            ->assertActionHidden('requestReturn')
            ->assertSee(__('filament-resources.territory_assignment.return_requested_heading', [
                'date' => $this->assignment->fresh()->return_requested_at->format('d/m/Y H:i'),
            ]));

        expect($this->assignment->fresh()->return_requested_at)->not->toBeNull();

        Notification::assertSentTo([$this->overseer, $this->assistant], TerritoryReturnRequestedNotification::class);
    });

    test('the department cannot close the assignment from the shared link', function () {
        config(['app.guest_allow_returning_assignment' => true]);
        $this->assignment->requestReturn();

        Livewire::test(ViewSharedAssignment::class, ['record' => $this->sharedUrl->uid])
            ->assertActionHidden('requestReturn')
            ->assertActionHidden(TestAction::make('returnRequestedAssignment')->schemaComponent('returnRequestedCallout', 'assignmentInfolist'));
    });
});
