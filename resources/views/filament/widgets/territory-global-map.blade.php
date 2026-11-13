@php
    use App\Enums\TerritoryMapMode;
    use Filament\Support\Icons\Heroicon;

    $config = $this->getMapData();
    $legend = $this->getLegend();
@endphp

<x-filament-widgets::widget class="fi-wi-territory-global-map">
    <x-filament::section :heading="$this->getHeading()">
        <x-slot name="afterHeader">
            <div class="flex flex-wrap items-center justify-end gap-3">
                @if ($this->mapMode === TerritoryMapMode::CAMPAIGN)
                    <x-filament::input.wrapper
                        :prefix-icon="Heroicon::OutlinedEnvelopeOpen"
                        wire:target="campaignId"
                    >
                        <x-filament::input.select
                            :aria-label="__('filament-widgets.territory_global_map.campaign')"
                            wire:model.live="campaignId"
                        >
                            @foreach ($this->getCampaignOptions() as $campaignId => $campaignName)
                                <option value="{{ $campaignId }}">
                                    {{ $campaignName }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @endif

                <x-filament::input.wrapper
                    :prefix-icon="Heroicon::OutlinedSwatch"
                    wire:target="mapMode"
                >
                    <x-filament::input.select
                        :aria-label="__('filament-widgets.territory_global_map.map_mode')"
                        wire:model.live="mapMode"
                    >
                        @foreach ($this->getMapModeOptions() as $mapMode => $mapModeLabel)
                            <option value="{{ $mapMode }}">
                                {{ $mapModeLabel }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-slot>

        <x-filament-leaflet::map
            :config="$config"
            widget
        />

        @if (filled($legend))
            <ul
                aria-label="{{ __('filament-widgets.territory_global_map.legend') }}"
                class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-700 dark:text-gray-300"
            >
                @foreach ($legend as $legendItem)
                    <li class="flex items-center gap-2">
                        <span
                            class="inline-block size-3 shrink-0 rounded-full"
                            style="background-color: {{ $legendItem['color'] }}"
                        ></span>
                        <span>{{ $legendItem['label'] }}</span>
                        <span class="text-gray-500 dark:text-gray-400">({{ $legendItem['count'] }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
