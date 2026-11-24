<?php

use App\Filament\Resources\TerritoryAssignments\Pages\ManageTerritoryAssignments;
use App\Filament\Resources\TerritoryAssignments\Pages\ViewTerritoryAssignment;
use App\Models\Campaign;
use App\Models\Territory;
use App\Models\TerritoryAssignment;
use App\Models\TerritoryZone;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');
    $this->travelTo(Carbon::parse('2026-03-20 10:00:00'));

    User::factory()->create(['id' => 1]);

    $this->assignee = User::factory()->create(['can_login' => true]);
    $this->assignee->givePermissionTo(Permission::findOrCreate('ViewAny:TerritoryAssignment', 'web'));
    $this->actingAs($this->assignee);

    $this->campaign = Campaign::factory()->create(['valid_from' => now()->subMonth(), 'valid_until' => Carbon::parse('2026-03-23 18:00:00')]);

    $this->assignment = TerritoryAssignment::factory()->create([
        'territory_id' => Territory::factory()->for(TerritoryZone::factory(), 'territoryZone')->create()->id,
        'assignee_id' => $this->assignee->id,
        'campaign_id' => $this->campaign->id,
        'started_at' => Carbon::parse('2026-03-05 14:30:00'),
        'ended_at' => null,
    ]);
});

test('the table shows the dates in the format of the locale', function (string $locale, string $expected) {
    app()->setLocale($locale);

    Livewire::test(ManageTerritoryAssignments::class)
        ->assertSee($expected)
        ->assertDontSee('05/03/2026 14:30');
})->with([
    'italian' => ['it', '5 marzo 2026 14:30'],
    'english' => ['en', 'March 5, 2026 2:30 PM'],
]);

test('the assignment page shows its dates and its deadline in the format of the locale', function (string $locale, string $started, string $deadline) {
    app()->setLocale($locale);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertSee($started)
        ->assertSee($deadline)
        ->assertDontSee('2026-03-05 14:30:00')
        ->assertDontSee('23/03/2026');
})->with([
    'italian' => ['it', '5 marzo 2026 14:30', '23 marzo 2026'],
    'english' => ['en', 'March 5, 2026 2:30 PM', 'March 23, 2026'],
]);

test('the banner of a return request shows its date in the format of the locale', function (string $locale, string $expected) {
    app()->setLocale($locale);
    $this->assignment->update(['return_requested_at' => Carbon::parse('2026-03-18 09:15:00')]);

    Livewire::test(ViewTerritoryAssignment::class, ['record' => $this->assignment->getKey()])
        ->assertSee(__('filament-resources.territory_assignment.return_requested_heading', ['date' => $expected]))
        ->assertDontSee('18/03/2026 09:15');
})->with([
    'italian' => ['it', '18 marzo 2026 09:15'],
    'english' => ['en', 'March 18, 2026 9:15 AM'],
]);
