<?php

namespace Database\Seeders;

use App\Enums\DepartmentKey;
use App\Enums\DepartmentRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Give some members to each department: the territory servants serve in the territory department, and two other
 * users (an overseer and an assistant) in each of the other departments.
 */
class FakeDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()->whereKeyNot(1)->orderBy('id')->get();

        $territoryServants = $users->filter(fn (User $user): bool => $user->hasRole('territory_servant'))->values();
        $others = $users->reject(fn (User $user): bool => $user->hasRole(['territory_servant', 'service_overseer']))->values();

        $this->addMembers(DepartmentKey::Territories, $territoryServants->all());
        $this->addMembers(DepartmentKey::PublicWitnessing, $others->slice(0, 2)->all());
        $this->addMembers(DepartmentKey::Publications, $others->slice(2, 2)->all());
    }

    /**
     * Add the given users to a department: the first one as overseer, the others as assistants.
     *
     * @param  array<int, User>  $users
     */
    private function addMembers(DepartmentKey $key, array $users): void
    {
        $department = Department::for($key);

        foreach (array_values($users) as $index => $user) {
            $department->members()->syncWithoutDetaching([
                $user->getKey() => ['role' => $index === 0 ? DepartmentRole::Overseer : DepartmentRole::Assistant],
            ]);
        }
    }
}
