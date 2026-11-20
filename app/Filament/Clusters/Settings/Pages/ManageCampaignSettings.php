<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\AppSettingsPage;
use App\Settings\CampaignSettings;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageCampaignSettings extends AppSettingsPage
{
    protected static string $settings = CampaignSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'campaigns';

    public static function getNavigationLabel(): string
    {
        return __('settings.pages.campaigns');
    }

    public function getTitle(): string
    {
        return __('settings.pages.campaigns');
    }

    public function form(Schema $schema): Schema
    {
        $stages = [
            ['name' => __('filament-resources.campaign.status_active'), 'color' => 'var(--success-500)'],
            ['name' => __('filament-resources.campaign.status_is_expiring'), 'color' => 'var(--warning-500)', 'field' => 'expiring_days'],
            ['name' => __('filament-resources.campaign.status_almost_expired'), 'color' => 'var(--danger-500)', 'field' => 'almost_expired_days'],
            ['name' => __('filament-resources.campaign.status_terminated'), 'color' => 'var(--gray-400)'],
        ];
        $unit = __('settings.units.days_before_end');

        return $schema
            ->components([
                Section::make(__('settings.sections.campaign_expiry'))
                    ->description(__('settings.sections.campaign_expiry_description'))
                    ->columnSpanFull()
                    ->schema([
                        $this->stageRow($stages[0]['name'], $stages[0]['color'], __('settings.stages.active_note')),
                        $this->stageInput('expiring_days', $stages[1]['name'], $stages[1]['color'], $unit)
                            ->minValue(1)
                            ->maxValue(365),
                        $this->stageInput('almost_expired_days', $stages[2]['name'], $stages[2]['color'], $unit)
                            ->minValue(1)
                            ->lt('expiring_days'),
                        $this->stageRow($stages[3]['name'], $stages[3]['color'], __('settings.stages.terminated_note')),
                        $this->stagesBar($stages, isCountdown: true, startLabel: '', endLabel: __('settings.stages.end_date')),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return array_map('intval', parent::mutateFormDataBeforeSave($data));
    }
}
