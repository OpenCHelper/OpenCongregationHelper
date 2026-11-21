@php
    $assignmentsCount = $this->getAssignmentsCount();
    $preferredTerritories = $this->getPreferredTerritories();
    $sections = [
        [
            'heading' => __('filament-widgets.publisher_statistics.preferred_zones'),
            'description' => __('filament-widgets.publisher_statistics.preferred_zones_description'),
            'items' => $this->getPreferredZones(),
        ],
        [
            'heading' => __('filament-widgets.publisher_statistics.preferred_territories'),
            'description' => trans_choice('filament-widgets.publisher_statistics.preferred_territories_description', $preferredTerritories->count()),
            'items' => $preferredTerritories,
        ],
    ];
@endphp

<x-filament-widgets::widget class="fi-wi-publisher-statistics">
    <x-filament::section
        :heading="__('filament-widgets.publisher_statistics.heading')"
        :description="trans_choice('filament-widgets.publisher_statistics.assignments_count', $assignmentsCount)"
        :icon="\Filament\Support\Icons\Heroicon::OutlinedChartBar"
    >
        @if ($assignmentsCount === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('filament-widgets.publisher_statistics.no_assignments') }}
            </p>
        @else
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($sections as $section)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $section['heading'] }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $section['description'] }}
                        </p>

                        <ol class="mt-3 flex flex-col gap-3">
                            @foreach ($section['items'] as $item)
                                <li>
                                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-sm">
                                        <span class="flex min-w-0 grow items-center gap-2">
                                            @if (filled($item['color'] ?? null))
                                                <span
                                                    class="inline-block size-3 shrink-0 rounded-full"
                                                    style="background-color: {{ $item['color'] }}"
                                                ></span>
                                            @endif

                                            <span class="flex min-w-0 flex-col">
                                                @if (filled($item['url'] ?? null))
                                                    <a
                                                        href="{{ $item['url'] }}"
                                                        title="{{ $item['name'] }}"
                                                        class="truncate font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                    >{{ $item['name'] }}</a>
                                                @else
                                                    <span
                                                        title="{{ $item['name'] }}"
                                                        class="truncate font-medium text-gray-950 dark:text-white"
                                                    >{{ $item['name'] }}</span>
                                                @endif

                                                @if (filled($item['zone'] ?? null))
                                                    <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['zone'] }}</span>
                                                @endif
                                            </span>
                                        </span>

                                        <span class="ms-auto shrink-0 text-gray-700 dark:text-gray-300">
                                            {{ trans_choice('filament-widgets.publisher_statistics.assignments_count', $item['count']) }}
                                            <span class="text-gray-500 dark:text-gray-400">({{ \Illuminate\Support\Number::percentage($item['percentage'], maxPrecision: 1, locale: app()->getLocale()) }})</span>
                                        </span>
                                    </div>

                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                        <div
                                            class="h-full rounded-full bg-primary-500"
                                            @if (filled($item['color'] ?? null))
                                                style="width: {{ $item['percentage'] }}%; background-color: {{ $item['color'] }}"
                                            @else
                                                style="width: {{ $item['percentage'] }}%"
                                            @endif
                                        ></div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
