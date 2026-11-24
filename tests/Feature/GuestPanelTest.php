<?php

use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Support\Facades\Notification;

test('the root of the guest panel redirects to the welcome page', function () {
    $this->get('/guest')->assertRedirect('/');
});

test('the guest panel has no dashboard nor widgets', function () {
    $panel = Filament::getPanel('guest');

    expect($panel->getPages())->not->toContain(Dashboard::class)
        ->and($panel->getWidgets())->not->toContain(FilamentInfoWidget::class)
        ->and($panel->hasNavigation())->toBeFalse();
});

test('the shared pages of the guest panel do not show the Filament version', function () {
    $this->withoutVite();
    Notification::fake();

    User::factory()->create(['id' => 1]);

    $assignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()->id,
        'assignee_id' => User::factory()->create()->id,
        'ended_at' => null,
    ]);

    $this->get('/guest/shared-assignment/'.$assignment->share()->uid)
        ->assertOk()
        ->assertDontSee('filament.io')
        ->assertDontSee('fi-wi-filament-info');
});
