<?php

use App\Enums\CampaignStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The thresholds come from the settings, read from the database
pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-03-15 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('status depends on the days remaining until the end date', function (?Carbon $validUntil, CampaignStatus $expectedStatus) {
    expect(CampaignStatus::fromValidUntil($validUntil))->toBe($expectedStatus);
})->with([
    'no end date' => [null, CampaignStatus::ACTIVE],
    '50 days left' => [fn (): Carbon => now()->addDays(50), CampaignStatus::ACTIVE],
    '8 days left' => [fn (): Carbon => now()->addDays(8), CampaignStatus::ACTIVE],
    '7 days left' => [fn (): Carbon => now()->addDays(7), CampaignStatus::IS_EXPIRING],
    '5 days left' => [fn (): Carbon => now()->addDays(5), CampaignStatus::IS_EXPIRING],
    '3 days left' => [fn (): Carbon => now()->addDays(3), CampaignStatus::ALMOST_EXPIRED],
    '2 days left' => [fn (): Carbon => now()->addDays(2), CampaignStatus::ALMOST_EXPIRED],
    'one hour left' => [fn (): Carbon => now()->addHour(), CampaignStatus::ALMOST_EXPIRED],
    'ended one hour ago' => [fn (): Carbon => now()->subHour(), CampaignStatus::TERMINATED],
    'ended 10 days ago' => [fn (): Carbon => now()->subDays(10), CampaignStatus::TERMINATED],
]);
