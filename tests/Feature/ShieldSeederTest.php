<?php

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Models\Poster;
use App\Models\PublicationSubscriber;
use App\Models\Tag;
use App\Models\Territory;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tapp\FilamentMailLog\Models\MailLog;

/**
 * The permissions checked in the code (`->can('Action:Subject')`), e.g. by the policies.
 *
 * @return list<string>
 */
function permissionsCheckedInTheCode(): array
{
    return collect(File::allFiles(app_path()))
        ->flatMap(function (SplFileInfo $file): array {
            preg_match_all("/->can\\('([A-Za-z]+:[A-Za-z]+)'\\)/", (string) file_get_contents($file->getPathname()), $matches);

            return $matches[1];
        })
        ->unique()
        ->sort()
        ->values()
        ->all();
}

beforeEach(function () {
    Filament::setCurrentPanel('app');
});

test('the shield seeder uses the permission names checked by the policies', function () {
    $this->seed(ShieldSeeder::class);

    $superAdminPermissions = Role::findByName('super_admin', 'web')->permissions->pluck('name');

    expect($superAdminPermissions)
        ->toContain('ViewAny:TerritoryAssignment', 'Create:Campaign', 'Update:User', 'Update:Department')
        ->and(Permission::where('name', 'like', '%::%')->exists())->toBeFalse();
});

test('every permission checked by a policy exists after seeding and is offered in the role editor', function () {
    $this->seed(ShieldSeeder::class);

    $checked = permissionsCheckedInTheCode();
    $offered = array_keys(FilamentShield::getAllResourcePermissionsWithLabels());

    expect($checked)->toContain('ViewAny:Tag', 'Update:PublicationRequest', 'Reorder:Publication', 'Delete:Role')
        ->and($checked)->each->toBeIn($offered)
        ->and($checked)->each->toBeIn(Permission::pluck('name')->all())
        ->and($checked)->each->toBeIn(Role::findByName('super_admin', 'web')->permissions->pluck('name')->all());

    User::factory()->create(['id' => 1]);
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));

    $form = Livewire::test(CreateRole::class);

    foreach ($checked as $permission) {
        $form->assertSeeHtml('value="'.$permission.'"');
    }
});

test('the shield seeder creates only the permissions offered in the role editor', function () {
    $this->seed(ShieldSeeder::class);

    expect(Permission::pluck('name')->sort()->values()->all())
        ->toBe(collect(FilamentShield::getAllResourcePermissionsWithLabels())->keys()->sort()->values()->all())
        ->and(Permission::pluck('name')->all())->not->toContain(
            'View:TerritoryGlobalMap', 'ViewAny:Activity', 'ViewAny:MailLog', 'Restore:Role', 'Replicate:Territory',
            'Reorder:Tag', 'ForceDelete:Poster', 'Delete:PublicationSubscriber',
        );
});

test('the shield seeder creates the permissions of the app panel from another panel', function () {
    // As when the setup wizard creates the database (nothing has read the permissions of the app panel yet)
    Filament::setCurrentPanel('setup');

    $this->seed(ShieldSeeder::class);

    expect(Filament::getCurrentPanel()->getId())->toBe('setup')
        ->and(Permission::pluck('name')->all())->toContain('ViewAny:Territory', 'Update:Department', 'Delete:Role')
        ->and(Permission::pluck('guard_name')->unique()->all())->toBe(['web']);

    Filament::setCurrentPanel('app');

    expect(Permission::count())->toBe(count(ShieldSeeder::permissionNames()));
});

test('the shield seeder ignores the resources that Shield already read from another panel', function () {
    Filament::setCurrentPanel('setup');

    // Shield keeps the (empty) resources of the setup panel for the rest of the request
    expect(FilamentShield::getResources())->toBeEmpty();

    $this->seed(ShieldSeeder::class);

    expect(Permission::pluck('name')->all())->toContain('ViewAny:Territory', 'Update:Department', 'Delete:Role')
        ->and(FilamentShield::getResources())->toBeEmpty();
});

test('seeding twice keeps the permissions of the super administrators', function () {
    $this->seed(ShieldSeeder::class);
    $this->seed(ShieldSeeder::class);

    expect(Role::where('name', 'super_admin')->count())->toBe(1)
        ->and(Role::findByName('super_admin', 'web')->permissions)->toHaveCount(count(ShieldSeeder::permissionNames()));
});

test('the actions that a resource does not offer are denied whatever the permissions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
        [
            'Restore:Poster', 'Replicate:Territory', 'Reorder:Tag', 'Delete:PublicationSubscriber', 'RestoreAny:Role',
            'ViewAny:Activity', 'ViewAny:MailLog',
        ],
    ));

    expect($user->can('restore', new Poster))->toBeFalse()
        ->and($user->can('replicate', new Territory))->toBeFalse()
        ->and($user->can('reorder', Tag::class))->toBeFalse()
        ->and($user->can('delete', new PublicationSubscriber))->toBeFalse()
        ->and($user->can('restoreAny', Role::class))->toBeFalse()
        ->and($user->can('viewAny', Activity::class))->toBeFalse()
        ->and($user->can('viewAny', MailLog::class))->toBeFalse();
});
