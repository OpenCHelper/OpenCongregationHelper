<?php

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Departments\Pages\ListDepartments;
use App\Filament\Resources\Departments\Pages\ViewDepartment;
use App\Filament\Resources\Departments\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Department;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
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

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findOrCreate('super_admin', 'web'));
});

/**
 * Add the user to the department with the given role.
 */
function joinDepartment(User $user, DepartmentKey $key, DepartmentRole $role = DepartmentRole::Assistant): Department
{
    $department = Department::for($key);
    $department->members()->attach($user, ['role' => $role]);

    return $department;
}

test('every department exists after the migrations', function () {
    expect(Department::query()->ordered()->pluck('key')->all())->toBe(DepartmentKey::cases());
});

test('the departments are listed in the order of their keys, and cannot be created or deleted', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListDepartments::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Department::query()->ordered()->get(), inOrder: true)
        ->assertActionDoesNotExist('create');

    expect(DepartmentResource::canCreate())->toBeFalse()
        ->and(DepartmentResource::canDelete(Department::for(DepartmentKey::Territories)))->toBeFalse();
});

test('the overseers of a department can fully manage its sections, and only them', function () {
    $member = User::factory()->create();
    joinDepartment($member, DepartmentKey::Territories, DepartmentRole::Overseer);

    $this->actingAs($member);

    expect($member->can('ForceDelete:Territory'))->toBeTrue()
        ->and($member->can('ViewAny:TerritoryZone'))->toBeTrue()
        ->and(TerritoryResource::canViewAny())->toBeTrue()
        ->and(TerritoryResource::canCreate())->toBeTrue()
        ->and(TerritoryResource::canDelete(Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()))->toBeTrue()
        // The rest of the application needs a role, as before
        ->and($member->can('ViewAny:Tag'))->toBeFalse()
        ->and($member->can('ViewAny:User'))->toBeFalse()
        ->and($member->can('ViewAny:Department'))->toBeFalse();
});

test('the assistants of a department manage its sections but cannot delete, unless their roles allow it', function () {
    $assistant = User::factory()->create();
    joinDepartment($assistant, DepartmentKey::Territories);
    $territory = Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create();

    $this->actingAs($assistant);

    expect(TerritoryResource::canViewAny())->toBeTrue()
        ->and(TerritoryResource::canCreate())->toBeTrue()
        ->and(TerritoryResource::canEdit($territory))->toBeTrue()
        ->and(TerritoryResource::canDelete($territory))->toBeFalse()
        ->and(TerritoryResource::canDeleteAny())->toBeFalse();

    foreach (User::OVERSEER_ONLY_ACTIONS as $action) {
        expect($assistant->can("{$action}:Territory"))->toBeFalse();
    }

    // The permissions of their roles still apply
    $assistant->givePermissionTo(Permission::findOrCreate('Delete:Territory', 'web'));

    expect($assistant->fresh()->can('Delete:Territory'))->toBeTrue();
});

test('the territory department grants the territories, zones, assignments, campaigns and global map', function () {
    $member = User::factory()->create();
    joinDepartment($member, DepartmentKey::Territories);

    foreach (['Territory', 'TerritoryZone', 'TerritoryAssignment', 'Campaign', 'TerritoryGlobalMap'] as $subject) {
        expect($member->can("Update:{$subject}"))->toBeTrue();
    }

    expect($member->can('Update:Tag'))->toBeFalse();
});

test('users without departments keep the permissions of their roles', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:Territory', 'web'));

    expect($user->can('ViewAny:Territory'))->toBeTrue()
        ->and($user->can('Create:Territory'))->toBeFalse();
});

test('administrators can add members to a department with a role', function () {
    $this->actingAs($this->admin);

    $department = Department::for(DepartmentKey::Territories);
    [$overseer, $assistant] = User::factory()->count(2)->create();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$overseer->id], 'role' => DepartmentRole::Overseer->value])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$assistant->id], 'role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors()
        ->assertCanSeeTableRecords([$overseer, $assistant], inOrder: true);

    expect($department->overseers()->pluck('users.id')->all())->toBe([$overseer->id])
        ->and($assistant->fresh()->isMemberOf(DepartmentKey::Territories))->toBeTrue();
});

test('administrators can change the role of a member and remove them', function () {
    $this->actingAs($this->admin);

    $member = User::factory()->create();
    $department = joinDepartment($member, DepartmentKey::Territories);

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('edit')->table($member), ['role' => DepartmentRole::Overseer->value])
        ->assertHasNoFormErrors();

    expect($department->isOverseer($member))->toBeTrue();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('detach')->table($member));

    expect($department->members()->exists())->toBeFalse();
});

test('the overseers manage the members of their department', function () {
    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::Territories, DepartmentRole::Overseer);

    $this->actingAs($overseer);

    expect($overseer->can('view', $department))->toBeTrue()
        ->and($overseer->can('update', $department))->toBeTrue()
        ->and(DepartmentResource::canViewAny())->toBeTrue();

    $newMember = User::factory()->create();

    $this->get(DepartmentResource::getUrl('view', ['record' => $department]))->assertOk();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$newMember->id], 'role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors();

    expect($newMember->fresh()->isMemberOf(DepartmentKey::Territories))->toBeTrue();
});

test('the overseers add and manage assistants, but only the administrators appoint, change or remove overseers', function () {
    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::Territories, DepartmentRole::Overseer);
    $otherOverseer = User::factory()->create();
    joinDepartment($otherOverseer, DepartmentKey::Territories, DepartmentRole::Overseer);
    $assistant = User::factory()->create();
    joinDepartment($assistant, DepartmentKey::Territories);
    [$newOverseer, $newAssistant] = User::factory()->count(2)->create();

    $this->actingAs($overseer);
    $component = fn () => Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class]);

    $component()
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$newOverseer->id], 'role' => DepartmentRole::Overseer->value])
        ->assertHasFormErrors(['role']);
    $component()
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$newAssistant->id], 'role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors();
    $component()
        ->assertActionHidden(TestAction::make('edit')->table($otherOverseer))
        ->assertActionHidden(TestAction::make('detach')->table($otherOverseer))
        ->assertActionVisible(TestAction::make('detach')->table($assistant))
        ->callAction(TestAction::make('edit')->table($assistant), ['role' => DepartmentRole::Overseer->value])
        ->assertHasFormErrors(['role']);
    $component()->selectTableRecords([$otherOverseer, $assistant])->callAction(TestAction::make('detach')->table()->bulk());

    expect($department->overseers()->pluck('users.id')->sort()->values()->all())->toBe([$overseer->id, $otherOverseer->id])
        ->and($newOverseer->fresh()->isMemberOf(DepartmentKey::Territories))->toBeFalse()
        ->and($newAssistant->fresh()->isMemberOf(DepartmentKey::Territories))->toBeTrue()
        ->and($assistant->fresh()->isMemberOf(DepartmentKey::Territories))->toBeFalse();

    // An administrator can
    $this->actingAs($this->admin);

    $component()
        ->callAction(TestAction::make('edit')->table($otherOverseer), ['role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors();

    expect($department->isOverseer($otherOverseer->fresh()))->toBeFalse();
});

test('the users to add are searched by name, not by email', function () {
    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::Territories, DepartmentRole::Overseer);
    User::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'secret@example.com']);

    $this->actingAs($overseer);

    $select = Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->mountAction(TestAction::make('attach')->table())
        ->instance()
        ->getMountedAction()
        ->getRecordSelect();

    expect($select->getSearchResults('Rossi'))->not->toBeEmpty()
        ->and($select->getSearchResults('secret@'))->toBeEmpty();
});

test('the assistants cannot manage the members of their department', function () {
    $assistant = User::factory()->create();
    $department = joinDepartment($assistant, DepartmentKey::Territories);

    $this->actingAs($assistant);

    expect($assistant->can('update', $department))->toBeFalse()
        ->and(DepartmentResource::canViewAny())->toBeFalse();

    $this->get(DepartmentResource::getUrl('view', ['record' => $department]))->assertForbidden();
});

test('the users list shows and filters the departments', function () {
    $this->actingAs($this->admin);

    $member = User::factory()->create();
    joinDepartment($member, DepartmentKey::Territories);
    $other = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertTableColumnStateSet('departments', [DepartmentKey::Territories], $member)
        ->filterTable('departments', [Department::for(DepartmentKey::Territories)->id])
        ->assertCanSeeTableRecords([$member])
        ->assertCanNotSeeTableRecords([$other]);
});

test('the department pages render', function () {
    $this->actingAs($this->admin);

    $department = joinDepartment(User::factory()->create(['first_name' => 'Andrea']), DepartmentKey::Territories, DepartmentRole::Overseer);

    $this->get(DepartmentResource::getUrl())->assertOk()->assertSee(DepartmentKey::Territories->getLabel())->assertSee('Andrea');
    $this->get(DepartmentResource::getUrl('view', ['record' => $department]))->assertOk()->assertSee(TerritoryResource::getNavigationLabel());
});

test('the departments are sorted with the table prefix of the installation', function () {
    $connection = Department::query()->getConnection();
    $prefix = $connection->getTablePrefix();
    $connection->setTablePrefix('p45_');

    try {
        $sql = Department::query()->ordered()->toSql();
    } finally {
        $connection->setTablePrefix($prefix);
    }

    expect($sql)->toContain($connection->getQueryGrammar()->wrap('p45_departments').'.');
});

test('the roles offer only the permissions on the departments that the policy can grant', function () {
    $permissions = collect(FilamentShield::getResources())
        ->firstWhere('resourceFqcn', DepartmentResource::class)['permissions'];

    expect(array_column($permissions, 'key'))->toBe(['ViewAny:Department', 'View:Department', 'Update:Department']);
});
