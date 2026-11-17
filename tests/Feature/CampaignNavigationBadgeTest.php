<?php

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    User::factory()->create(['id' => 1]);
});

test('the navigation badge counts the campaigns ending within a week', function () {
    Campaign::factory()->create(['valid_until' => now()->addDays(2)]);
    Campaign::factory()->create(['valid_until' => now()->addDays(6)]);
    Campaign::factory()->create(['valid_until' => now()->addDays(20)]);
    Campaign::factory()->create(['valid_until' => now()->subDay()]);
    Campaign::factory()->create(['valid_until' => null]);

    expect(CampaignResource::getNavigationBadge())->toBe('2')
        ->and(CampaignResource::getNavigationBadgeColor())->toBe('warning');
});

test('the navigation badge is hidden when no campaign is ending', function () {
    Campaign::factory()->create(['valid_until' => now()->addDays(20)]);
    Campaign::factory()->create(['valid_until' => now()->subDay()]);

    expect(CampaignResource::getNavigationBadge())->toBeNull();
});
