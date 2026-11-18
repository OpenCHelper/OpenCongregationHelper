<?php

use App\Filament\Resources\ServiceGroups\Pages\CreateServiceGroup;
use App\Filament\Resources\ServiceGroups\Pages\EditServiceGroup;
use App\Filament\Resources\ServiceGroups\Pages\ListServiceGroups;
use App\Filament\Resources\ServiceGroups\Pages\ManageServiceGroupMembers;
use App\Filament\Resources\ServiceGroups\Pages\OrganizeServiceGroups;
use App\Filament\Resources\ServiceGroups\ServiceGroupResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\ServiceGroup;
use App\Models\User;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $this->manager = User::factory()->create(['can_login' => true]);
    foreach ([
        'ViewAny:ServiceGroup', 'View:ServiceGroup', 'Create:ServiceGroup', 'Update:ServiceGroup', 'Delete:ServiceGroup',
        'ViewAny:User', 'View:User', 'Update:User',
    ] as $permission) {
        $this->manager->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->manager);

    $this->group = ServiceGroup::factory()->create(['name' => 'Group 1']);
    $this->otherGroup = ServiceGroup::factory()->create(['name' => 'Group 2']);
    $this->publisher = User::factory()->create(['service_group_id' => $this->group->id]);
});

test('a group is created with its coordinator and assistants', function () {
    [$coordinator, $assistant] = User::factory()->count(2)->create();

    Livewire::test(CreateServiceGroup::class)
        ->fillForm([
            'name' => 'Group 3',
            'coordinator_id' => $coordinator->id,
            'assistant_coordinator1_id' => $assistant->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $group = ServiceGroup::firstWhere('name', 'Group 3');

    expect($group->coordinator->is($coordinator))->toBeTrue()
        ->and($group->assistantCoordinator1->is($assistant))->toBeTrue()
        ->and($group->assistantCoordinator2)->toBeNull();
});

test('the same person cannot have two roles in a group', function () {
    Livewire::test(EditServiceGroup::class, ['record' => $this->group->id])
        ->fillForm([
            'coordinator_id' => $this->publisher->id,
            'assistant_coordinator2_id' => $this->publisher->id,
        ])
        ->call('save')
        ->assertHasFormErrors(['coordinator_id' => 'different', 'assistant_coordinator2_id' => 'different']);
});

test('the name of a group is unique', function () {
    Livewire::test(CreateServiceGroup::class)
        ->fillForm(['name' => 'Group 2'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

test('the list shows the groups with the number of their publishers', function () {
    Livewire::test(ListServiceGroups::class)
        ->assertCanSeeTableRecords([$this->group, $this->otherGroup])
        ->assertTableColumnStateSet('members_count', 1, $this->group)
        ->assertTableColumnStateSet('members_count', 0, $this->otherGroup);
});

test('deleting a group leaves its publishers without a group', function () {
    $this->group->delete();

    expect($this->publisher->refresh()->service_group_id)->toBeNull();
});

test('deleting a leader of a group leaves the role empty', function () {
    $this->group->coordinator()->associate($this->publisher)->save();

    $this->publisher->delete();

    expect($this->group->refresh()->coordinator_id)->toBeNull();
});

test('publishers are added to and removed from a group from its page', function () {
    $newPublisher = User::factory()->create(['service_group_id' => $this->otherGroup->id]);

    Livewire::test(ManageServiceGroupMembers::class, ['record' => $this->group->id])
        ->assertCanSeeTableRecords([$this->publisher])
        ->assertCanNotSeeTableRecords([$newPublisher])
        ->callAction(TestAction::make(AssociateAction::class)->table(), ['recordId' => [$newPublisher->id]])
        ->callAction(TestAction::make(DissociateAction::class)->table($this->publisher));

    expect($newPublisher->refresh()->service_group_id)->toBe($this->group->id)
        ->and($this->publisher->refresh()->service_group_id)->toBeNull();
});

test('the members page shows who leads the group', function () {
    $this->group->coordinator()->associate($this->publisher)->save();

    Livewire::test(ManageServiceGroupMembers::class, ['record' => $this->group->id])
        ->assertTableColumnStateSet('role_in_group', __('filament-resources.service_group.role_coordinator'), $this->publisher);
});

test('the service group is shown on the page of the publisher', function () {
    $this->group->assistantCoordinator1()->associate($this->publisher)->save();

    Livewire::test(ViewUser::class, ['record' => $this->publisher->id])
        ->assertSchemaStateSet(['service_group_id' => $this->group->id])
        ->assertSee('Group 1')
        ->assertSee(__('filament-resources.user.hint_service_group_role', ['role' => __('filament-resources.service_group.role_assistant')]));
});

test('the service group of a publisher is changed from the user form', function () {
    Livewire::test(EditUser::class, ['record' => $this->publisher->id])
        ->fillForm(['service_group_id' => $this->otherGroup->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->publisher->refresh()->service_group_id)->toBe($this->otherGroup->id);
});

test('users are filtered by service group', function () {
    $publisherWithoutGroup = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('service_group_id', [$this->group->id])
        ->assertCanSeeTableRecords([$this->publisher])
        ->assertCanNotSeeTableRecords([$publisherWithoutGroup]);
});

test('the board shows the publishers in the column of their group', function () {
    $publisherWithoutGroup = User::factory()->create(['first_name' => 'Ungrouped']);

    Livewire::test(OrganizeServiceGroups::class)
        ->assertOk()
        ->assertSeeInOrder([__('filament-resources.service_group.board_without_group'), 'Ungrouped', 'Group 1', $this->publisher->getFullName(), 'Group 2']);
});

test('publishers are moved between groups from the board', function () {
    Livewire::test(OrganizeServiceGroups::class)
        ->call('movePublisher', $this->publisher->id, $this->otherGroup->id)
        ->assertNotified(__('filament-resources.service_group.publisher_moved', ['name' => $this->publisher->getFullName(), 'group' => 'Group 2']));

    expect($this->publisher->refresh()->service_group_id)->toBe($this->otherGroup->id);

    Livewire::test(OrganizeServiceGroups::class)
        ->call('movePublisher', $this->publisher->id, null);

    expect($this->publisher->refresh()->service_group_id)->toBeNull();
});

test('the board needs the permission to edit users and groups', function () {
    $viewer = User::factory()->create(['can_login' => true]);
    $viewer->givePermissionTo(['ViewAny:ServiceGroup', 'View:ServiceGroup', 'ViewAny:User', 'Update:User']);
    $this->actingAs($viewer);

    // Can open the board, but not change the groups
    Livewire::test(OrganizeServiceGroups::class)
        ->call('movePublisher', $this->publisher->id, $this->otherGroup->id)
        ->assertForbidden();

    expect($this->publisher->refresh()->service_group_id)->toBe($this->group->id);

    $viewer->revokePermissionTo('Update:User');

    $this->get(ServiceGroupResource::getUrl('organize'))->assertForbidden();
});

test('several users are moved to a group at once from the users table', function () {
    $publishers = User::factory()->count(2)->create();

    Livewire::test(ListUsers::class)
        ->selectTableRecords($publishers)
        ->callAction(TestAction::make('changeServiceGroup')->table()->bulk(), ['service_group_id' => $this->otherGroup->id])
        ->assertNotified(trans_choice('filament-resources.user.service_group_changed', 2));

    expect($publishers->map(fn (User $publisher) => $publisher->refresh()->service_group_id)->unique()->all())->toBe([$this->otherGroup->id]);
});

test('only English titles capitalize every word of the label', function (string $locale, string $title) {
    app()->setLocale($locale);

    expect(ServiceGroupResource::getTitleCasePluralModelLabel())->toBe($title);
})->with([
    'English' => ['en', 'Service Groups'],
    'Italian' => ['it', 'Gruppi di servizio'],
]);
