<?php

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    User::factory()->create(['id' => 1]);

    $this->assignee = User::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);

    $territory = Territory::factory()
        ->for(TerritoryZone::factory(), 'territoryZone')
        ->create(['name' => 'Shared territory', 'notes' => 'Private territory notes']);

    $this->assignment = TerritoryAssignment::factory()->create([
        'territory_id' => $territory->id,
        'assignee_id' => $this->assignee->id,
        'ended_at' => null,
    ]);

    $this->sharedUrl = $this->assignment->share();
});

test('shared assignment pages render with the built frontend assets', function () {
    $this->get('/guest/shared-assignment/'.$this->sharedUrl->uid)
        ->assertOk();
});

test('shared assignment pages hide personal data by default', function () {
    $this->withoutVite();
    config(['app.enable_shared_pii' => false]);

    $this->get('/guest/shared-assignment/'.$this->sharedUrl->uid)
        ->assertOk()
        ->assertSee('Shared territory')
        ->assertDontSee('Mario Rossi')
        ->assertDontSee('Private territory notes');
});

test('shared assignment pages show personal data when enabled', function () {
    $this->withoutVite();
    config(['app.enable_shared_pii' => true]);

    $this->get('/guest/shared-assignment/'.$this->sharedUrl->uid)
        ->assertOk()
        ->assertSee('Mario Rossi')
        ->assertSee('Private territory notes');
});
