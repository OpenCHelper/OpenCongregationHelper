<?php

namespace Database\Seeders;

use App\Models\ServiceGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class FakeServiceGroupSeeder extends Seeder
{
    /**
     * Gather the users in a few service groups, each led by one of its publishers.
     */
    public function run(): void
    {
        $groups = collect(range(1, 3))->map(fn (int $number): ServiceGroup => ServiceGroup::factory()->create(['name' => "Group {$number}"]));

        // The first user (the root user) is left without a group
        User::query()->orderBy('id')->get()
            ->skip(1)
            ->values()
            ->each(fn (User $user, int $index) => $user->update(['service_group_id' => $groups[$index % $groups->count()]->id]));

        $groups->each(function (ServiceGroup $group): void {
            [$coordinator, $assistant] = $group->members()->orderBy('id')->take(2)->get()->pad(2, null);

            $group->update([
                'coordinator_id' => $coordinator?->id,
                'assistant_coordinator1_id' => $assistant?->id,
            ]);
        });
    }
}
