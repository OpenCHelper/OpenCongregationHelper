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
use App\Models\User;
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
        ->and(DepartmentResource::canDelete(Department::for(DepartmentKey::Publications)))->toBeFalse();
});

test('the members of a department can fully manage its sections, and only them', function () {
    $member = User::factory()->create();
    joinDepartment($member, DepartmentKey::Territories);

    $this->actingAs($member);

    expect($member->can('ForceDelete:Territory'))->toBeTrue()
        ->and(TerritoryResource::canViewAny())->toBeTrue()
        ->and(TerritoryResource::canCreate())->toBeTrue()
        // The administration needs a role, as before
        ->and($member->can('ViewAny:User'))->toBeFalse()
        ->and($member->can('ViewAny:Department'))->toBeFalse();
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

    $department = Department::for(DepartmentKey::Publications);
    [$overseer, $assistant] = User::factory()->count(2)->create();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$overseer->id], 'role' => DepartmentRole::Overseer->value])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$assistant->id], 'role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors()
        ->assertCanSeeTableRecords([$overseer, $assistant], inOrder: true);

    expect($department->overseers()->pluck('users.id')->all())->toBe([$overseer->id])
        ->and($assistant->fresh()->isMemberOf(DepartmentKey::Publications))->toBeTrue()
        ->and($assistant->fresh()->isMemberOf(DepartmentKey::Territories))->toBeFalse();
});

test('administrators can change the role of a member and remove them', function () {
    $this->actingAs($this->admin);

    $member = User::factory()->create();
    $department = joinDepartment($member, DepartmentKey::Publications);

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('edit')->table($member), ['role' => DepartmentRole::Overseer->value])
        ->assertHasNoFormErrors();

    expect($department->isOverseer($member))->toBeTrue();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('detach')->table($member));

    expect($department->members()->exists())->toBeFalse();
});

test('the overseers manage the members of their own department only', function () {
    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::PublicWitnessing, DepartmentRole::Overseer);
    $other = Department::for(DepartmentKey::Publications);

    $this->actingAs($overseer);

    expect($overseer->can('view', $department))->toBeTrue()
        ->and($overseer->can('update', $department))->toBeTrue()
        ->and($overseer->can('view', $other))->toBeFalse()
        ->and($overseer->can('update', $other))->toBeFalse()
        ->and(DepartmentResource::canViewAny())->toBeTrue();

    $newMember = User::factory()->create();

    $this->get(DepartmentResource::getUrl('view', ['record' => $department]))->assertOk();
    $this->get(DepartmentResource::getUrl('view', ['record' => $other]))->assertForbidden();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$newMember->id], 'role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors();

    expect($newMember->fresh()->isMemberOf(DepartmentKey::PublicWitnessing))->toBeTrue();
});

test('the assistants cannot manage the members of their department', function () {
    $assistant = User::factory()->create();
    $department = joinDepartment($assistant, DepartmentKey::PublicWitnessing);

    $this->actingAs($assistant);

    expect($assistant->can('update', $department))->toBeFalse()
        ->and(DepartmentResource::canViewAny())->toBeFalse();

    $this->get(DepartmentResource::getUrl('view', ['record' => $department]))->assertForbidden();
});

test('the users list shows and filters the departments', function () {
    $this->actingAs($this->admin);

    $member = User::factory()->create();
    joinDepartment($member, DepartmentKey::Publications);
    $other = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertTableColumnStateSet('departments', [DepartmentKey::Publications], $member)
        ->filterTable('departments', [Department::for(DepartmentKey::Publications)->id])
        ->assertCanSeeTableRecords([$member])
        ->assertCanNotSeeTableRecords([$other]);
});

test('the department pages render', function () {
    $this->actingAs($this->admin);

    $department = joinDepartment(User::factory()->create(['first_name' => 'Andrea']), DepartmentKey::PublicWitnessing, DepartmentRole::Overseer);

    $this->get(DepartmentResource::getUrl())->assertOk()->assertSee(DepartmentKey::PublicWitnessing->getLabel())->assertSee('Andrea');
    $this->get(DepartmentResource::getUrl('view', ['record' => $department]))->assertOk()->assertSee(__('departments.members.title'));
});
