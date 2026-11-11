<?php

use App\Filament\Pages\ApiTokens;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Laravel\Jetstream\Features;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

test('listing users through the api requires the ViewAny:User permission', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/assignees')->assertForbidden();
});

test('users with the ViewAny:User permission can list users through the api', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::create(['name' => 'ViewAny:User']));

    Sanctum::actingAs($user);

    $this->getJson('/api/assignees')->assertOk();
});

test('listing assignments through the api requires the ViewAny:TerritoryAssignment permission', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/assignments')->assertForbidden();
});

test('territory and zone api endpoints are authorized', function (string $path) {
    $zone = TerritoryZone::factory()->create();
    $territory = Territory::factory()->for($zone, 'territoryZone')->create();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson(str_replace(['{territory}', '{zone}'], [$territory->id, $zone->id], $path))
        ->assertForbidden();
})->with([
    '/api/territories',
    '/api/territories/{territory}',
    '/api/zones',
    '/api/zones/{zone}',
]);

test('the territory api does not expose write endpoints', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/territories', ['name' => 'New'])->assertMethodNotAllowed();
});

test('the api tokens page is not accessible when the api feature is disabled', function () {
    expect(Features::hasApiFeatures())->toBeFalse()
        ->and(ApiTokens::canAccess())->toBeFalse();
});
