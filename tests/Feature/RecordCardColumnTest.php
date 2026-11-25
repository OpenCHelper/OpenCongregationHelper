<?php

use App\Enums\CampaignStatus;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Tables\Columns\RecordCardColumn;
use App\Models\Campaign;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
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

test('on phones the table is stacked and shows only the card of each record', function () {
    $table = Livewire::test(ListCampaigns::class)->instance()->getTable();

    $card = $table->getColumn('card');

    expect($table->isStackedOnMobile())->toBeTrue()
        ->and($card)->toBeInstanceOf(RecordCardColumn::class)
        ->and($card->getHiddenFrom())->toBe('sm')
        // Last, so that on larger screens the first visible column keeps its padding
        ->and(array_key_last($table->getColumns()))->toBe('card')
        ->and(collect($table->getColumns())->except('card')->map(fn (Column $column): ?string => $column->getVisibleFrom())->unique()->values()->all())
        ->toBe(['sm']);
});

test('the card has the heading and the status of the record, and its other enabled columns as details', function () {
    $creator = User::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);
    $campaign = Campaign::factory()->create([
        'name' => 'Letter writing',
        'short_name' => 'LTR',
        'description' => 'Letters to the neighbours',
        'created_by' => $creator->id,
    ]);

    $component = Livewire::test(ListCampaigns::class);
    $renderCard = fn (): string => $component->instance()->getTable()->getColumn('card')->record($campaign)->toHtml();

    expect($renderCard())
        ->toContain('fi-ta-record-card-heading')
        ->toContain('Letter writing')
        ->toContain(CampaignStatus::fromCampaign($campaign)->getLabel())
        ->toContain(__('filament-resources.campaign.column_short_name'))
        ->toContain('LTR')
        ->toContain('Mario Rossi')
        // Hidden by default in the columns menu
        ->not->toContain('Letters to the neighbours');

    $component->toggleAllTableColumns();

    expect($renderCard())->toContain('Letters to the neighbours');
});

test('the card leaves out the details without a value', function () {
    // Without a creator (the acting user is set as creator when the campaign is created)
    $campaign = Campaign::factory()->create();
    Campaign::query()->whereKey($campaign)->update(['created_by' => null]);
    $campaign->refresh();

    $html = Livewire::test(ListCampaigns::class)
        ->instance()->getTable()->getColumn('card')->record($campaign)->toHtml();

    expect($html)
        ->toContain(__('filament-resources.campaign.column_short_name'))
        ->not->toContain(__('filament-resources.campaign.column_creator'));
});

test('the card is not listed in the columns menu', function () {
    $card = Livewire::test(ListCampaigns::class)->instance()->getTable()->getColumn('card');

    // The columns menu leaves out the columns without a label
    expect($card->getLabel())->toBeEmpty()
        ->and($card->isToggleable())->toBeFalse();
});
