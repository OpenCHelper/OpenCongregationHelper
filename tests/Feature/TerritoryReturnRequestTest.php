<?php

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Filament\Clusters\Settings\Pages\ManageTerritorySettings;
use App\Filament\Guest\Pages\ViewSharedAssignment;
use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Filament\Resources\TerritoryAssignments\TerritoryAssignmentResource;
use App\Models\Department;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use App\Notifications\TerritoryReturnRequestedNotification;
use App\Settings\TerritorySettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

test('the breadcrumb links to the list of the assignments only for the users who can open it', function () {
    $this->actingAs($this->assignee);

    $assigneeBreadcrumbs = Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->instance()
        ->getBreadcrumbs();

    expect($assigneeBreadcrumbs)->not->toHaveKey(TerritoryAssignmentResource::getUrl())
        ->and($assigneeBreadcrumbs)->toContain(TerritoryAssignmentResource::getBreadcrumb());

    $this->actingAs($this->overseer);

    expect(Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])->instance()->getBreadcrumbs())
        ->toHaveKey(TerritoryAssignmentResource::getUrl());
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

test('a publisher without permissions can share their own assignment, and stop sharing it', function () {
    $this->actingAs($this->assignee);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionVisible('share')
        ->mountAction('share')
        ->assertActionMounted('share');

    expect($this->assignment->fresh()->shared)->not->toBeNull();

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->callAction(['share', 'stop_sharing'])
        ->assertNotified(__('filament-resources.territory_assignment.share_modal.stop_sharing_success'));

    expect($this->assignment->fresh()->shared)->toBeNull();
});

test('only those allowed to view the assignments and the assignee can share an assignment', function () {
    $otherPublisher = User::factory()->create(['can_login' => true]);
    $this->actingAs($otherPublisher);

    expect(ViewTerritoryAssignment::canManageSharing($this->assignment))->toBeFalse();

    // Nor can they open the assignment, to reach the actions
    $this->withoutVite()
        ->get(TerritoryAssignmentResource::getUrl('view', ['record' => $this->assignment]))
        ->assertNotFound();

    $this->actingAs($this->assignee);

    expect(ViewTerritoryAssignment::canManageSharing($this->assignment))->toBeTrue();
});

test('the users allowed to view the assignments can still share them', function () {
    $staff = User::factory()->create(['can_login' => true]);
    $staff->givePermissionTo([
        Permission::findOrCreate('ViewAny:TerritoryAssignment', 'web'),
        Permission::findOrCreate('View:TerritoryAssignment', 'web'),
    ]);
    $this->actingAs($staff);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertActionVisible('share')
        ->mountAction('share')
        ->assertActionMounted('share');

    expect($this->assignment->fresh()->shared)->not->toBeNull();
});

test('asking again from a page opened before the return was asked tells it was already asked', function (string $meanwhile) {
    $this->actingAs($this->assignee);

    $staleTab = Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->mountAction('requestReturn');

    match ($meanwhile) {
        'asked' => $this->assignment->requestReturn($this->assignee),
        'returned' => $this->assignment->update(['ended_at' => now()]),
    };

    $staleTab->callMountedAction()
        ->assertNotified(__('filament-resources.territory_assignment.request_return_failure'))
        ->assertActionNotMounted('requestReturn');
})->with(['asked', 'returned']);

test('reopening a returned assignment forgets its return request', function () {
    $this->assignment->requestReturn($this->assignee);
    $this->assignment->update(['ended_at' => now()]);

    $this->assignment->update(['ended_at' => null]);

    expect($this->assignment->fresh()->return_requested_at)->toBeNull()
        ->and($this->assignment->fresh()->canRequestReturn())->toBeTrue();
});

test('giving the assignment to someone else forgets its pending return request', function () {
    $this->assignment->requestReturn($this->assignee);

    $this->assignment->update(['assignee_id' => User::factory()->create()->id]);

    expect($this->assignment->fresh()->return_requested_at)->toBeNull();
});

describe('from the shared link', function () {
    beforeEach(function () {
        Filament::setCurrentPanel('guest');

        $this->sharedUrl = $this->assignment->share();

        // Sharing loads the settings: forget them, so that they follow the configuration set by each test
        app()->forgetScopedInstances();
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

    test('the setting changed from the Settings pages wins over the environment', function (bool $configured, bool $stored) {
        config(['app.guest_allow_returning_assignment' => $configured]);
        $settings = app(TerritorySettings::class);
        $settings->guest_returns = $stored;
        $settings->save();

        $component = Livewire::test(ViewSharedAssignment::class, ['record' => $this->sharedUrl->uid]);

        $stored ? $component->assertActionVisible('requestReturn') : $component->assertActionHidden('requestReturn');
    })->with([
        'enabled over the environment' => [false, true],
        'disabled over the environment' => [true, false],
    ]);

    test('asking again from a shared page opened before the return was asked tells it was already asked', function () {
        config(['app.guest_allow_returning_assignment' => true]);

        $staleTab = Livewire::test(ViewSharedAssignment::class, ['record' => $this->sharedUrl->uid])
            ->mountAction('requestReturn');

        $this->assignment->requestReturn();

        $staleTab->callMountedAction()
            ->assertNotified(__('filament-resources.territory_assignment.request_return_failure'));
    });

    test('asking to return from the shared link sends back none of the data of the assignment', function (bool $alreadyAsked) {
        config(['app.guest_allow_returning_assignment' => true]);
        $this->assignment->update(['notes' => 'Private assignment notes']);

        $component = Livewire::test(ViewSharedAssignment::class, ['record' => $this->sharedUrl->uid])
            ->mountAction('requestReturn');

        if ($alreadyAsked) {
            $this->assignment->requestReturn();
        }

        $result = $component->instance()->callMountedAction();

        expect($result)->toBeNull()
            ->and(json_encode($component->instance()->getMountedAction()?->toArray() ?? []))->not->toContain('Private assignment notes');
    })->with(['first time' => false, 'already asked' => true]);

    test('the department cannot close the assignment from the shared link', function () {
        config(['app.guest_allow_returning_assignment' => true]);
        $this->assignment->requestReturn();

        Livewire::test(ViewSharedAssignment::class, ['record' => $this->sharedUrl->uid])
            ->assertActionHidden('requestReturn')
            ->assertActionHidden(TestAction::make('returnRequestedAssignment')->schemaComponent('returnRequestedCallout', 'assignmentInfolist'));
    });
});

describe('on the Settings pages', function () {
    beforeEach(function () {
        $superAdmin = User::factory()->create(['can_login' => true]);
        $superAdmin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($superAdmin);
    });

    test('the setting shows the value of the environment until changed', function (bool $configured) {
        config(['app.guest_allow_returning_assignment' => $configured]);

        Livewire::test(ManageTerritorySettings::class)
            ->assertSee(__('settings.fields.guest_returns.label'))
            ->assertSchemaStateSet(['guest_returns' => $configured]);

        expect(app(TerritorySettings::class)->guest_returns)->toBe($configured);
    })->with([true, false]);

    test('the super administrators enable the return requests from the shared pages', function () {
        config(['app.guest_allow_returning_assignment' => false]);

        Livewire::test(ManageTerritorySettings::class)
            ->fillForm(['guest_returns' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(app(TerritorySettings::class)->guest_returns)->toBeTrue();
    });
});
