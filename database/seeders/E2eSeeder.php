<?php

namespace Database\Seeders;

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed the data of the end-to-end tests (see `tests/e2e`), after `och:install`: the fake data of a congregation, plus a
 * user for each role and a few records with known names, that the tests look for.
 *
 * Faker is seeded, so that every run has the same data.
 */
class E2eSeeder extends Seeder
{
    /**
     * The password of every user created here (the root user has `INITIAL_ROOT_PASSWORD`).
     */
    public const PASSWORD = 'password';

    /**
     * The users of the tests, by email, with their name and role (`null` for none).
     *
     * @var array<string, array{first_name: string, last_name: string, role: ?string}>
     */
    public const USERS = [
        'admin@e2e.test' => ['first_name' => 'Adam', 'last_name' => 'Admin', 'role' => 'super_admin'],
        'servant@e2e.test' => ['first_name' => 'Simon', 'last_name' => 'Servant', 'role' => 'territory_servant'],
        'overseer@e2e.test' => ['first_name' => 'Oscar', 'last_name' => 'Overseer', 'role' => 'service_overseer'],
        'group@e2e.test' => ['first_name' => 'Gino', 'last_name' => 'Group', 'role' => 'group_overseer'],
        'publisher@e2e.test' => ['first_name' => 'Paul', 'last_name' => 'Publisher', 'role' => 'publisher'],
        'norole@e2e.test' => ['first_name' => 'Nora', 'last_name' => 'Norole', 'role' => null],
    ];

    /**
     * The other users of the congregation, without a login of their own.
     */
    public const OTHER_USERS_COUNT = 8;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        fake()->seed(20261127);

        // The roles first: `FakeRoleSeeder` gives a role to the users that have none
        $this->call(FakeRoleSeeder::class);

        $users = $this->createUsers();

        User::factory(self::OTHER_USERS_COUNT)->create()
            ->each(fn (User $user) => $user->assignRole('publisher'));

        $this->call([
            FakeTagSeeder::class,
            FakeCampaignSeeder::class,
            FakeTerritorySeeder::class,
            FakeTerritoryAssignmentSeeder::class,
            FakePosterSeeder::class,
            FakePublicationSubscriberSeeder::class,
            FakePublicationRequestSeeder::class,
        ]);

        $this->createDepartments($users);
        $this->createTerritories($users);
    }

    /**
     * @return array<string, User>
     */
    private function createUsers(): array
    {
        $users = [];

        foreach (self::USERS as $email => $data) {
            $user = User::factory()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'can_login' => true,
                // The tests read the pages, emails and notifications in English
                'locale' => 'en',
            ]);

            if ($data['role'] !== null) {
                $user->assignRole($data['role']);
            }

            $users[$email] = $user;
        }

        return $users;
    }

    /**
     * The territory servant is the overseer of the territories department, who is notified of the return requests.
     *
     * @param  array<string, User>  $users
     */
    private function createDepartments(array $users): void
    {
        Department::for(DepartmentKey::Territories)->members()->syncWithoutDetaching([
            $users['servant@e2e.test']->getKey() => ['role' => DepartmentRole::Overseer],
        ]);

        Department::for(DepartmentKey::PublicWitnessing);
        Department::for(DepartmentKey::Publications);
    }

    /**
     * A zone with a free territory, a commercial one, one assigned to the publisher and one assigned in a campaign that
     * has ended since (overdue).
     *
     * @param  array<string, User>  $users
     */
    private function createTerritories(array $users): void
    {
        $publisher = $users['publisher@e2e.test'];
        $servant = $users['servant@e2e.test'];

        $zone = TerritoryZone::factory()->create([
            'name' => 'E2E Zone',
            'color' => '#00897b',
            'created_by' => $servant->getKey(),
        ]);

        $territory = fn (string $name, array $attributes = []): Territory => Territory::factory()->withMap()->for($zone)->create([
            'name' => $name,
            'description' => "{$name} description",
            'notes' => "{$name} notes",
            'created_by' => $servant->getKey(),
            ...$attributes,
        ]);

        $territory('E2E Free');
        $territory('E2E Commercial', ['is_commercial' => true]);
        $assigned = $territory('E2E Assigned');
        $overdue = $territory('E2E Overdue');

        $expiredCampaign = Campaign::factory()->create([
            'name' => 'E2E Expired Campaign',
            'short_name' => 'E2E-EXP',
            'valid_from' => now()->subMonths(3)->startOfDay(),
            'valid_until' => now()->addMonth()->endOfDay(),
            'created_by' => $servant->getKey(),
        ]);

        TerritoryAssignment::factory()->create([
            'territory_id' => $assigned->getKey(),
            'assignee_id' => $publisher->getKey(),
            'campaign_id' => null,
            'started_at' => now()->subDays(10),
            'ended_at' => null,
            'notes' => null,
            'created_by' => $servant->getKey(),
        ]);

        TerritoryAssignment::factory()->create([
            'territory_id' => $overdue->getKey(),
            'assignee_id' => $publisher->getKey(),
            'campaign_id' => $expiredCampaign->getKey(),
            'started_at' => now()->subMonths(2),
            'ended_at' => null,
            'notes' => null,
            'created_by' => $servant->getKey(),
        ]);

        // The campaign ends once the territory is assigned: an assignment cannot start in a terminated campaign
        Campaign::query()->whereKey($expiredCampaign->getKey())->update(['valid_until' => now()->subMonth()->endOfDay()]);
    }
}
