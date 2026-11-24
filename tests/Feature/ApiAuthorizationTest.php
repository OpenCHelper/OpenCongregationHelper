<?php

use App\Filament\Pages\ApiTokens;
use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Laravel\Jetstream\Features;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

test('the removed sanctum api endpoints no longer exist', function (string $path) {
    $zone = TerritoryZone::factory()->create();
    $territory = Territory::factory()->for($zone, 'territoryZone')->create();

    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));
    Sanctum::actingAs($user);

    $this->getJson(str_replace(['{territory}', '{zone}'], [$territory->id, $zone->id], $path))
        ->assertNotFound();
})->with([
    '/api/user',
    '/api/assignees',
    '/api/assignments',
    '/api/territories',
    '/api/territories/{territory}',
    '/api/zones',
    '/api/zones/{zone}',
]);

test('the api tokens page is not accessible when the api feature is disabled', function () {
    expect(Features::hasApiFeatures())->toBeFalse()
        ->and(ApiTokens::canAccess())->toBeFalse();
});
