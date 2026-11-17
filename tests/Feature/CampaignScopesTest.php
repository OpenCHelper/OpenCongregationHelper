<?php

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    User::factory()->create(['id' => 1]);

    $this->activeCampaign = Campaign::factory()->create([
        'name' => 'Active',
        'valid_from' => now()->subDays(5),
        'valid_until' => now()->addDays(5),
    ]);
    $this->openEndedCampaign = Campaign::factory()->create([
        'name' => 'Open ended',
        'valid_from' => null,
        'valid_until' => null,
    ]);
    $this->futureCampaign = Campaign::factory()->create([
        'name' => 'Future',
        'valid_from' => now()->addDays(5),
        'valid_until' => now()->addDays(10),
    ]);
    $this->expiredCampaign = Campaign::factory()->create([
        'name' => 'Expired',
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->subDays(5),
    ]);
});

test('not terminated scope excludes only expired campaigns', function () {
    expect(Campaign::notTerminated()->pluck('name')->all())
        ->toEqualCanonicalizing(['Active', 'Open ended', 'Future']);
});

test('active scope only includes started and not terminated campaigns', function () {
    expect(Campaign::withActive()->pluck('name')->all())
        ->toEqualCanonicalizing(['Active', 'Open ended']);
});

test('expired scope only includes terminated campaigns', function () {
    expect(Campaign::withExpired()->pluck('name')->all())
        ->toEqual(['Expired']);
});

test('scopes keep previous where clauses when chained', function () {
    expect(Campaign::where('name', 'Active')->withActive()->pluck('name')->all())
        ->toEqual(['Active']);

    expect(Campaign::where('name', 'Expired')->withActive()->pluck('name')->all())
        ->toBeEmpty();

    expect(Campaign::where('name', 'Active')->withExpired()->pluck('name')->all())
        ->toBeEmpty();
});

test('expiring scope only includes campaigns ending within a week', function () {
    expect(Campaign::expiring()->pluck('name')->all())->toBe(['Active']);
});
