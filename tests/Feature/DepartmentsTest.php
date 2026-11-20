<?php

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Departments\Pages\ListDepartments;
use App\Filament\Resources\Departments\Pages\ViewDepartment;
use App\Filament\Resources\Departments\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Posters\PosterResource;
use App\Filament\Resources\Territories\TerritoryResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Department;
use App\Models\Poster;
use App\Models\User;
use App\Policies\Custom\DepartmentPolicy;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
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
    joinDepartment($member, DepartmentKey::PublicWitnessing, DepartmentRole::Overseer);

    $this->actingAs($member);

    expect($member->can('ForceDelete:Poster'))->toBeTrue()
        ->and($member->can('ViewAny:StorageLocation'))->toBeTrue()
        ->and(PosterResource::canViewAny())->toBeTrue()
        ->and(PosterResource::canCreate())->toBeTrue()
        ->and(PosterResource::canDelete(Poster::factory()->create()))->toBeTrue()
        // Other departments and the administration need a role, as before
        ->and($member->can('ViewAny:TerritoryZone'))->toBeFalse()
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

    $department = Department::for(DepartmentKey::PublicWitnessing);
    [$overseer, $assistant] = User::factory()->count(2)->create();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $department, 'pageClass' => ViewDepartment::class])
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$overseer->id], 'role' => DepartmentRole::Overseer->value])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('attach')->table(), ['recordId' => [$assistant->id], 'role' => DepartmentRole::Assistant->value])
        ->assertHasNoFormErrors()
        ->assertCanSeeTableRecords([$overseer, $assistant], inOrder: true);

    expect($department->overseers()->pluck('users.id')->all())->toBe([$overseer->id])
        ->and($assistant->fresh()->isMemberOf(DepartmentKey::PublicWitnessing))->toBeTrue()
        ->and($assistant->fresh()->isMemberOf(DepartmentKey::Territories))->toBeFalse();
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

test('the overseers manage the members of their own department only', function () {
    $overseer = User::factory()->create();
    $department = joinDepartment($overseer, DepartmentKey::PublicWitnessing, DepartmentRole::Overseer);
    $other = Department::for(DepartmentKey::Territories);

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
    $department = joinDepartment($assistant, DepartmentKey::PublicWitnessing);

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

test('the policy of the departments is not replaced when the permissions are generated again', function () {
    // `shield:generate` skips a model whose policy is registered from outside the path it writes to
    expect(Gate::getPolicyFor(Department::class))->toBeInstanceOf(DepartmentPolicy::class)
        ->and(class_exists(Utils::resolvePolicyFor(Department::class)))->toBeFalse();

    $generated = app_path('Policies/DepartmentPolicy.php');

    try {
        $this->artisan('shield:generate', ['--resource' => 'DepartmentResource', '--option' => 'policies', '--panel' => 'app'])
            ->assertSuccessful();

        expect(File::exists($generated))->toBeFalse();
    } finally {
        File::delete($generated);
    }
});
