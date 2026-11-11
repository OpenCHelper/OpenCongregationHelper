<?php

use App\Models\Team;
use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

test('deleting a user removes their team memberships', function () {
    User::factory()->create(['id' => 1]);

    $owner = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);

    $member = User::factory()->create();
    $team->users()->attach($member, ['role' => 'editor']);

    app(DeletesUsers::class)->delete($member);

    expect($member->fresh())->toBeNull();

    // The membership table has no foreign keys, so the rows must be removed explicitly
    $this->assertDatabaseMissing('team_user', ['user_id' => $member->id]);
    $this->assertDatabaseHas('teams', ['id' => $team->id]);
});
