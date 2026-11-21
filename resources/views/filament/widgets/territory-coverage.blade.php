@php
    use Filament\Support\Icons\Heroicon;

    $coverage = $this->getCoverage();
    $coverageExcludingCampaigns = $this->getCoverageExcludingCampaigns();
    $leastRecentlyWorked = $this->getLeastRecentlyWorkedTerritories();
@endphp

<x-filament-widgets::widget class="fi-wi-territory-coverage">
    <x-filament::section :heading="__('filament-widgets.territory_coverage.heading')">
        <x-slot name="afterHeader">
            <x-filament::input.wrapper :prefix-icon="Heroicon::OutlinedCalendarDays" wire:target="months">
                <x-filament::input.select
                    :aria-label="__('filament-widgets.territory_coverage.period_label')"
                    wire:model.live="months"
                >
                    @foreach ($this->getPeriodOptions() as $months => $label)
                        <option value="{{ $months }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </x-slot>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="flex flex-col gap-4">
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('filament-widgets.territory_coverage.worked') }}
                    </div>
                    <div class="mt-1 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white" data-coverage>
                        {{ $coverage['label'] }}
                    </div>
                    <div
                        class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $coverage['percentage'] }}"
                    >
                        <div
                            class="h-full rounded-full {{ $coverage['percentage'] >= 100 ? 'bg-success-500' : 'bg-primary-500' }}"
                            style="width: {{ $coverage['percentage'] }}%"
                        ></div>
                    </div>
                </div>

                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('filament-widgets.territory_coverage.worked_excluding_campaigns') }}
                    </div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white" data-coverage-excluding-campaigns>
                        {{ $coverageExcludingCampaigns['label'] }}
                    </div>
                </div>
            </div>

            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('filament-widgets.territory_coverage.least_recently_worked') }}
                </div>

                @if ($leastRecentlyWorked->isEmpty())
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('filament-widgets.territory_coverage.no_territories') }}
                    </p>
                @else
                    <ul class="mt-2 divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($leastRecentlyWorked as $territory)
                            @php
                                $lastWorkedAt = $this->getLastWorkedAt($territory);
                                $url = $this->getTerritoryUrl($territory);
                            @endphp

                            <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2 text-sm">
                                <span
                                    class="min-w-0 max-w-full truncate"
                                    title="{{ $territory->name }}"
                                >
                                    @if ($url)
                                        <a href="{{ $url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $territory->name }}</a>
                                    @else
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $territory->name }}</span>
                                    @endif

                                    @if ($territory->territoryZone)
                                        <span class="text-gray-500 dark:text-gray-400">· {{ $territory->territoryZone->name }}</span>
                                    @endif
                                </span>

                                @if ($territory->is_in_progress)
                                    <x-filament::badge color="info">
                                        {{ __('filament-widgets.territory_coverage.in_progress') }}
                                    </x-filament::badge>
                                @elseif ($lastWorkedAt)
                                    <span class="shrink-0 text-gray-500 dark:text-gray-400" title="{{ $lastWorkedAt->isoFormat('LL') }}">
                                        {{ __('filament-widgets.territory_coverage.last_worked', ['date' => $lastWorkedAt->isoFormat('LL'), 'ago' => $lastWorkedAt->diffForHumans()]) }}
                                    </span>
                                @else
                                    <x-filament::badge color="danger">
                                        {{ __('filament-widgets.territory_coverage.never_worked') }}
                                    </x-filament::badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
