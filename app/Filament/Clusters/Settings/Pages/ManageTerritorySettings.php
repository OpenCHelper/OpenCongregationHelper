<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\AppSettingsPage;
use App\Settings\TerritorySettings;
use BackedEnum;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

class ManageTerritorySettings extends AppSettingsPage
{
    protected static string $settings = TerritorySettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'territories';

    public static function getNavigationLabel(): string
    {
        return __('settings.pages.territories');
    }

    public function getTitle(): string
    {
        return __('settings.pages.territories');
    }

    public function form(Schema $schema): Schema
    {
        $staleStages = [
            ['name' => __('settings.stages.in_progress'), 'color' => 'var(--gray-400)'],
            ['name' => __('settings.fields.stale_after_days.label'), 'color' => Color::Yellow[500], 'field' => 'stale_after_days'],
            ['name' => __('settings.fields.dead_after_days.label'), 'color' => Color::Neutral[900], 'field' => 'dead_after_days'],
        ];
        $unit = __('settings.units.days_after_assignment');

        $frequencyStages = [
            ['name' => __('settings.stages.low_frequency'), 'color' => Color::Red[500]],
            ['name' => __('settings.fields.medium_frequency_min_assignments.label'), 'color' => Color::Orange[500], 'field' => 'medium_frequency_min_assignments'],
            ['name' => __('settings.fields.high_frequency_min_assignments.label'), 'color' => Color::Green[500], 'field' => 'high_frequency_min_assignments'],
        ];
        $assignmentsUnit = __('settings.units.assignments');

        return $schema
            ->components([
                Section::make(__('settings.sections.maps_and_sharing'))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('map_default_center')
                            ->label(__('settings.fields.map_default_center.label'))
                            ->placeholder('41.159063, -74.255188')
                            ->helperText(__('settings.fields.map_default_center.helper').' '.$this->defaultHint(
                                self::formatCoordinates($this->defaultValue('map_default_center')),
                            ))
                            ->required()
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (self::parseCoordinates((string) $value) === null) {
                                    $fail(__('settings.fields.map_default_center.invalid'));
                                }
                            }),
                        Toggle::make('shared_pii')
                            ->label(__('settings.fields.shared_pii.label'))
                            ->helperText(__('settings.fields.shared_pii.helper').' '.$this->defaultHint(
                                $this->defaultValue('shared_pii') ? __('settings.on') : __('settings.off'),
                            )),
                    ]),
                Section::make(__('settings.sections.stale_assignments'))
                    ->description(__('settings.sections.stale_assignments_description'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        $this->stageRow($staleStages[0]['name'], $staleStages[0]['color'], __('settings.stages.in_progress_note')),
                        $this->stageInput('stale_after_days', $staleStages[1]['name'], $staleStages[1]['color'], $unit)
                            ->minValue(1)
                            ->maxValue(3650),
                        $this->stageInput('dead_after_days', $staleStages[2]['name'], $staleStages[2]['color'], $unit)
                            ->maxValue(3650)
                            ->gt('stale_after_days'),
                        $this->stagesBar($staleStages, isCountdown: false, startLabel: __('settings.stages.assigned'), endLabel: ''),
                        Select::make('stale_check_day')
                            ->label(__('settings.fields.stale_check_day.label'))
                            ->helperText($this->defaultHint(self::dayOptions()[$this->defaultValue('stale_check_day')]))
                            ->options(self::dayOptions())
                            ->required()
                            ->native(false),
                        TextInput::make('stale_check_time')
                            ->label(__('settings.fields.stale_check_time.label'))
                            ->helperText(__('settings.fields.stale_check_time.helper').' '.$this->defaultHint($this->defaultValue('stale_check_time')))
                            ->type('time')
                            ->required()
                            ->regex('/^([01]\d|2[0-3]):[0-5]\d$/'),
                    ]),
                Section::make(__('settings.sections.assignment_frequency'))
                    ->description(__('settings.sections.assignment_frequency_description'))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('frequency_period_months')
                            ->label(__('settings.fields.frequency_period_months.label'))
                            ->helperText(__('settings.fields.frequency_period_months.helper').' '.$this->defaultHint((string) $this->defaultValue('frequency_period_months')))
                            ->integer()
                            ->required()
                            ->minValue(1)
                            ->maxValue(120)
                            ->suffix(__('settings.units.months')),
                        $this->stageRow($frequencyStages[0]['name'], $frequencyStages[0]['color'], __('settings.stages.low_frequency_note')),
                        $this->stageInput('medium_frequency_min_assignments', $frequencyStages[1]['name'], $frequencyStages[1]['color'], $assignmentsUnit)
                            ->minValue(1)
                            ->maxValue(999),
                        $this->stageInput('high_frequency_min_assignments', $frequencyStages[2]['name'], $frequencyStages[2]['color'], $assignmentsUnit)
                            ->maxValue(999)
                            ->gt('medium_frequency_min_assignments'),
                        $this->stagesBar($frequencyStages, isCountdown: false, startLabel: '0', endLabel: ''),
                    ]),
            ]);
    }

    /**
     * The days of the week, keyed by number (0 = Sunday), in the language of the user.
     *
     * @return array<int, string>
     */
    public static function dayOptions(): array
    {
        return collect(range(0, 6))
            ->mapWithKeys(fn (int $day): array => [$day => ucfirst(Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($day)->translatedFormat('l'))])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);
        $data['map_default_center'] = self::formatCoordinates($data['map_default_center']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['map_default_center'] = self::parseCoordinates((string) $data['map_default_center']);
        $data['shared_pii'] = (bool) $data['shared_pii'];
        $data['stale_check_day'] = (int) $data['stale_check_day'];

        foreach (['stale_after_days', 'dead_after_days', 'frequency_period_months', 'medium_frequency_min_assignments', 'high_frequency_min_assignments'] as $property) {
            $data[$property] = (int) $data[$property];
        }

        return parent::mutateFormDataBeforeSave($data);
    }

    /**
     * Parse coordinates written as "latitude, longitude", if they are valid.
     *
     * @return array{float, float}|null
     */
    public static function parseCoordinates(string $value): ?array
    {
        $parts = array_map('trim', explode(',', $value));

        if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            return null;
        }

        [$latitude, $longitude] = [(float) $parts[0], (float) $parts[1]];

        if (abs($latitude) > 90 || abs($longitude) > 180) {
            return null;
        }

        return [$latitude, $longitude];
    }

    /**
     * Write coordinates as "latitude, longitude".
     *
     * @param  array{float, float}  $coordinates
     */
    protected static function formatCoordinates(array $coordinates): string
    {
        return implode(', ', $coordinates);
    }
}
