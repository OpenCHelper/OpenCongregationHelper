<?php

use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Models\Campaign;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    User::factory()->create(['id' => 1]);

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:Campaign', 'web'));
    $this->actingAs($user);
});

test('campaigns can be sorted by creator', function () {
    $zoe = User::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Bianchi']);
    $anna = User::factory()->create(['first_name' => 'Anna', 'last_name' => 'Verdi']);

    $createdByZoe = Campaign::factory()->create(['created_by' => $zoe->id]);
    $createdByAnna = Campaign::factory()->create(['created_by' => $anna->id]);

    Livewire::test(ListCampaigns::class)
        ->toggleAllTableColumns()
        ->sortTable('creator.full_name')
        ->assertCanSeeTableRecords([$createdByAnna, $createdByZoe], inOrder: true)
        ->sortTable('creator.full_name', 'desc')
        ->assertCanSeeTableRecords([$createdByZoe, $createdByAnna], inOrder: true);
});
