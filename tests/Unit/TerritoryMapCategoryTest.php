<?php

use App\Enums\TerritoryMapCategory;
use App\Enums\TerritoryMapMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The thresholds come from the settings, read from the database
pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('the current status depends on whether an assignment is in progress', function () {
    expect(TerritoryMapCategory::fromOpenAssignment(false))->toBe(TerritoryMapCategory::AVAILABLE)
        ->and(TerritoryMapCategory::fromOpenAssignment(true))->toBe(TerritoryMapCategory::NOT_AVAILABLE);
});

test('the assignment frequency depends on the assignments of the last 12 months', function (int $recentAssignmentsCount, TerritoryMapCategory $expectedCategory) {
    expect(TerritoryMapCategory::fromRecentAssignmentsCount($recentAssignmentsCount))->toBe($expectedCategory);
})->with([
    'never assigned' => [0, TerritoryMapCategory::LOW_FREQUENCY],
    'assigned once' => [1, TerritoryMapCategory::MEDIUM_FREQUENCY],
    'assigned twice' => [2, TerritoryMapCategory::HIGH_FREQUENCY],
    'assigned five times' => [5, TerritoryMapCategory::HIGH_FREQUENCY],
]);

test('the campaign progress depends on the assignments linked to the campaign', function (bool $isWorkedInCampaign, bool $isInProgressInCampaign, bool $hasOpenAssignment, TerritoryMapCategory $expectedCategory) {
    expect(TerritoryMapCategory::fromCampaignProgress($isWorkedInCampaign, $isInProgressInCampaign, $hasOpenAssignment))
        ->toBe($expectedCategory);
})->with([
    'never assigned for the campaign' => [false, false, false, TerritoryMapCategory::TO_BE_ASSIGNED],
    'assigned outside of the campaign' => [false, false, true, TerritoryMapCategory::OUT_FOR_OTHER_ASSIGNMENT],
    'assigned for the campaign' => [false, true, true, TerritoryMapCategory::IN_PROGRESS],
    'returned for the campaign' => [true, false, false, TerritoryMapCategory::WORKED],
    'returned for the campaign, then assigned again' => [true, false, true, TerritoryMapCategory::WORKED],
]);

test('each map mode colored by category has its own categories', function () {
    $categories = collect(TerritoryMapMode::cases())->flatMap(fn (TerritoryMapMode $mapMode): array => $mapMode->getCategories());

    expect(TerritoryMapMode::ZONE->getCategories())->toBeEmpty()
        ->and($categories->all())->toEqualCanonicalizing(TerritoryMapCategory::cases());
});
