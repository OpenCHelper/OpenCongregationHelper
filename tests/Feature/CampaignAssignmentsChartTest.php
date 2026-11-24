<?php

use App\Filament\Resources\Campaigns\Widgets\CampaignAssignmentsChart;
use App\Models\Campaign;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
});

test('each slice of the assignment status chart has its own color', function () {
    $chart = Livewire::test(CampaignAssignmentsChart::class, ['record' => Campaign::factory()->create()])
        ->assertOk()
        ->instance();

    $dataset = (fn (): array => $this->getData())->call($chart)['datasets'][0];

    expect($dataset['backgroundColor'])->toHaveCount(3)
        ->and(array_unique($dataset['backgroundColor']))->toHaveCount(3)
        ->and($dataset['borderColor'])->toHaveCount(3)
        ->and(array_unique($dataset['borderColor']))->toHaveCount(3)
        ->and($dataset['backgroundColor'])->toHaveCount(count($dataset['data']));
});
