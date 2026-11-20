<?php

use App\Actions\Jetstream\DeleteUser;
use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Departments\Pages\ListDepartments;
use App\Filament\Resources\Departments\Pages\ViewDepartment;
use App\Filament\Resources\Departments\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Posters\PosterResource;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Department;
use App\Models\DepartmentMember;
use App\Models\Poster;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
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

test('the overseers of a department can fully manage its sections, and only them', function () {
    $member = User::factory()->create();
    joinDepartment($member, DepartmentKey::PublicWitnessing, DepartmentRole::Overseer);

    $this->actingAs($member);

    expect($member->can('ForceDelete:Poster'))->toBeTrue()
        ->and($member->can('ViewAny:StorageLocation'))->toBeTrue()
        ->and(PosterResource::canViewAny())->toBeTrue()
        ->and(PosterResource::canCreate())->toBeTrue()
        ->and(PosterResource::canDelete(Poster::factory()->create()))->toBeTrue()
        // Other departments and the administration need a role, as before
        ->and($member->can('ViewAny:PublicationSubscriber'))->toBeFalse()
        ->and(TerritoryResource::canViewAny())->toBeFalse()
        ->and($member->can('ViewAny:User'))->toBeFalse()
        ->and($member->can('ViewAny:Department'))->toBeFalse();
});

test('the assistants of a department manage its sections but cannot delete, unless their roles allow it', function () {
    $assistant = User::factory()->create();
    joinDepartment($assistant, DepartmentKey::PublicWitnessing);
    $poster = Poster::factory()->create();

    $this->actingAs($assistant);

    expect(PosterResource::canViewAny())->toBeTrue()
        ->and(PosterResource::canCreate())->toBeTrue()
        ->and(PosterResource::canEdit($poster))->toBeTrue()
        ->and(PosterResource::canDelete($poster))->toBeFalse()
        ->and(PosterResource::canDeleteAny())->toBeFalse();

    foreach (User::OVERSEER_ONLY_ACTIONS as $action) {
        expect($assistant->can("{$action}:Poster"))->toBeFalse();
    }

    // The permissions of their roles still apply
    $assistant->givePermissionTo(Permission::findOrCreate('Delete:Poster', 'web'));

    expect($assistant->fresh()->can('Delete:Poster'))->toBeTrue();
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
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:Poster', 'web'));

    expect($user->can('ViewAny:Poster'))->toBeTrue()
        ->and($user->can('Create:Poster'))->toBeFalse();
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
    // Another overseer, so that the member can be removed
    joinDepartment(User::factory()->create(), DepartmentKey::Publications, DepartmentRole::Overseer);

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('edit')->table($member), ['role' => DepartmentRole::Overseer->value])
        ->assertHasNoFormErrors();

    expect($department->isOverseer($member))->toBeTrue();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('detach')->table($member));

    expect($department->members()->whereKey($member->id)->exists())->toBeFalse();
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

test('the overseers add and manage assistants, but only the administrators appoint, change or remove overseers', function () {
    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::Publications, DepartmentRole::Overseer);
    $otherOverseer = User::factory()->create();
    joinDepartment($otherOverseer, DepartmentKey::Publications, DepartmentRole::Overseer);
    $assistant = User::factory()->create();
    joinDepartment($assistant, DepartmentKey::Publications);
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
        ->and($newOverseer->fresh()->isMemberOf(DepartmentKey::Publications))->toBeFalse()
        ->and($newAssistant->fresh()->isMemberOf(DepartmentKey::Publications))->toBeTrue()
        ->and($assistant->fresh()->isMemberOf(DepartmentKey::Publications))->toBeFalse();

    // An administrator can
    $this->actingAs($this->admin);

    $component()
        ->callAction(TestAction::make('edit')->table($otherOverseer), ['role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors();

    expect($department->isOverseer($otherOverseer->fresh()))->toBeFalse();
});

test('the users to add are searched by name, not by email', function () {
    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::Publications, DepartmentRole::Overseer);
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
    $this->get(DepartmentResource::getUrl('view', ['record' => $department]))->assertOk()->assertSee(PosterResource::getNavigationLabel());
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

test('the overseers list only the departments they oversee, the administrators all of them', function () {
    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::PublicWitnessing, DepartmentRole::Overseer);

    $this->actingAs($overseer);

    Livewire::test(ListDepartments::class)
        ->assertCanSeeTableRecords([$department])
        ->assertCanNotSeeTableRecords([Department::for(DepartmentKey::Territories)]);

    $this->actingAs($this->admin);

    Livewire::test(ListDepartments::class)
        ->assertCanSeeTableRecords(Department::query()->get());
});

test('adding, changing and removing the members of a department is logged', function () {
    $this->actingAs($this->admin);

    $member = User::factory()->create();
    // Another overseer, so that the member can be removed, logged before
    $department = joinDepartment(User::factory()->create(), DepartmentKey::Territories, DepartmentRole::Overseer);
    Activity::query()->delete();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$member->id], 'role' => DepartmentRole::Assistant->value])
        ->callAction(TestAction::make('edit')->table($member), ['role' => DepartmentRole::Overseer->value])
        ->callAction(TestAction::make('detach')->table($member));

    $activities = Activity::query()->where('subject_type', (new DepartmentMember)->getMorphClass())->oldest('id')->get();

    expect($activities->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($activities->pluck('causer_id')->unique()->all())->toBe([$this->admin->id])
        ->and($activities[0]->properties['attributes'])->toMatchArray(['department_id' => $department->id, 'user_id' => $member->id, 'role' => 'assistant'])
        ->and($activities[1]->properties['attributes'])->toBe(['role' => 'overseer'])
        ->and($activities[2]->properties['old'])->toMatchArray(['user_id' => $member->id, 'role' => 'overseer']);
});

test('the only overseer of a department cannot be demoted or removed, not even by an administrator', function () {
    $this->actingAs($this->admin);

    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::Publications, DepartmentRole::Overseer);
    $assistant = User::factory()->create();
    joinDepartment($assistant, DepartmentKey::Publications);

    expect($department->isOnlyOverseer($overseer))->toBeTrue()
        ->and($department->isOnlyOverseer($assistant))->toBeFalse();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->assertActionDisabled(TestAction::make('edit')->table($overseer))
        ->assertSee(__('departments.members.last_overseer'))
        ->assertActionDisabled(TestAction::make('detach')->table($overseer))
        ->assertActionEnabled(TestAction::make('edit')->table($assistant))
        ->assertActionEnabled(TestAction::make('detach')->table($assistant))
        ->callAction(TestAction::make('edit')->table($overseer), ['role' => DepartmentRole::Assistant->value])
        ->callAction(TestAction::make('detach')->table($overseer));

    expect($department->isOnlyOverseer($overseer))->toBeTrue();
});

test('removing in bulk is refused when it would leave the department without an overseer', function () {
    $this->actingAs($this->admin);

    [$first, $second, $assistant] = User::factory()->count(3)->create();
    $department = joinDepartment($first, DepartmentKey::Publications, DepartmentRole::Overseer);
    joinDepartment($second, DepartmentKey::Publications, DepartmentRole::Overseer);
    joinDepartment($assistant, DepartmentKey::Publications);
    $component = fn () => Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class]);

    // Each one alone could go, both together cannot
    $component()
        ->selectTableRecords([$first, $second, $assistant])
        ->callAction(TestAction::make('detach')->table()->bulk())
        ->assertNotified(__('departments.members.last_overseer'));

    expect($department->members()->count())->toBe(3);

    $component()
        ->selectTableRecords([$first, $assistant])
        ->callAction(TestAction::make('detach')->table()->bulk());

    expect($department->members()->pluck('users.id')->all())->toBe([$second->id]);
});

test('a department with two overseers can still lose one of them', function () {
    $this->actingAs($this->admin);

    [$first, $second, $third] = User::factory()->count(3)->create();
    $department = joinDepartment($first, DepartmentKey::Publications, DepartmentRole::Overseer);
    joinDepartment($second, DepartmentKey::Publications, DepartmentRole::Overseer);
    joinDepartment($third, DepartmentKey::Publications, DepartmentRole::Overseer);
    $component = fn () => Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class]);

    $component()
        ->assertActionEnabled(TestAction::make('edit')->table($first))
        ->callAction(TestAction::make('edit')->table($first), ['role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors();
    $component()
        ->assertActionEnabled(TestAction::make('detach')->table($second))
        ->callAction(TestAction::make('detach')->table($second));

    expect($department->overseers()->pluck('users.id')->all())->toBe([$third->id])
        ->and($department->isOnlyOverseer($third))->toBeTrue();

    $user = User::factory()->create();
    joinDepartment($user, DepartmentKey::Territories, DepartmentRole::Overseer);
    joinDepartment(User::factory()->create(), DepartmentKey::Territories, DepartmentRole::Overseer);

    // Deleting an overseer who is not the last one removes their membership
    $user->delete();

    expect(Department::for(DepartmentKey::Territories)->overseers()->count())->toBe(1);
});

test('the memberships refuse to leave a department without an overseer, whatever the page', function () {
    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::Publications, DepartmentRole::Overseer);
    $assistant = User::factory()->create();
    joinDepartment($assistant, DepartmentKey::Publications);

    expect(fn () => $department->members()->updateExistingPivot($overseer->id, ['role' => DepartmentRole::Assistant]))
        ->toThrow(DomainException::class, __('departments.members.last_overseer'))
        ->and(fn () => $department->members()->detach($overseer))
        ->toThrow(DomainException::class)
        ->and(fn () => $department->members()->detach())
        ->toThrow(DomainException::class);

    // The assistants can still be removed, and departments that never had an overseer are left as they are
    $department->members()->detach($assistant);
    $other = joinDepartment(User::factory()->create(), DepartmentKey::Territories);
    $other->members()->detach();

    expect($department->isOnlyOverseer($overseer))->toBeTrue()
        ->and($department->members()->count())->toBe(1)
        ->and($other->members()->exists())->toBeFalse();
});

test('a department without an overseer can only be given an overseer first', function () {
    $this->actingAs($this->admin);

    $department = Department::for(DepartmentKey::Publications);
    [$assistant, $overseer] = User::factory()->count(2)->create();
    $component = fn () => Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class]);

    $component()
        ->mountAction(TestAction::make('attach')->table())
        ->assertSchemaStateSet(['role' => DepartmentRole::Overseer], 'mountedActionSchema0');
    $component()
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$assistant->id], 'role' => DepartmentRole::Assistant->value])
        ->assertHasFormErrors(['role']);

    expect($department->members()->exists())->toBeFalse();

    $component()
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$overseer->id], 'role' => DepartmentRole::Overseer->value])
        ->assertHasNoFormErrors();
    $component()
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$assistant->id], 'role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors();

    expect($department->isOverseer($overseer))->toBeTrue()
        ->and($assistant->fresh()->isMemberOf(DepartmentKey::Publications))->toBeTrue();
});

test('the only overseer of a department cannot be deleted', function () {
    $this->actingAs($this->admin);

    $overseer = User::factory()->create();
    joinDepartment($overseer, DepartmentKey::Publications, DepartmentRole::Overseer);
    $other = User::factory()->create();

    expect($overseer->isLastDepartmentOverseer())->toBeTrue()
        ->and($other->isLastDepartmentOverseer())->toBeFalse()
        ->and(fn () => $overseer->delete())->toThrow(Exception::class, __('departments.users.delete_last_overseer'))
        ->and(fn () => app(DeleteUser::class)->delete($overseer))->toThrow(Exception::class);

    $page = Livewire::test(EditUser::class, ['record' => $overseer->getRouteKey()])
        ->assertActionDisabled('delete');

    expect($page->instance()->getAction('delete')->getTooltip())->toBe(__('departments.users.delete_last_overseer'));

    Livewire::test(ViewUser::class, ['record' => $overseer->getRouteKey()])
        ->assertActionDisabled('delete');
    Livewire::test(EditUser::class, ['record' => $other->getRouteKey()])
        ->assertActionEnabled('delete');

    // In bulk, the only overseers are kept and the others deleted, also when the selection holds every overseer
    $coOverseer = User::factory()->create();
    joinDepartment($coOverseer, DepartmentKey::Publications, DepartmentRole::Overseer);

    Livewire::test(ListUsers::class)
        ->selectTableRecords([$overseer, $coOverseer, $other])
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertNotified(__('departments.users.bulk_delete_last_overseers', ['deleted' => 2]));

    expect($overseer->fresh())->toBeNull()
        ->and($other->fresh())->toBeNull()
        ->and($coOverseer->fresh())->not->toBeNull()
        ->and(Department::for(DepartmentKey::Publications)->isOnlyOverseer($coOverseer))->toBeTrue();
});

test('the only overseer of a department cannot delete their own account', function () {
    $overseer = User::factory()->create();
    joinDepartment($overseer, DepartmentKey::Publications, DepartmentRole::Overseer);

    $this->actingAs($overseer);

    $form = Livewire::test(DeleteUserForm::class)
        ->assertSee(__('departments.users.delete_account_last_overseer'))
        ->assertDontSee(__('profile.delete_account.confirm_deletion'))
        ->set('password', 'password');

    expect(fn () => $form->call('deleteUser'))->toThrow(Exception::class, __('departments.users.delete_last_overseer'))
        ->and($overseer->fresh())->not->toBeNull();
});
