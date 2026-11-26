{{-- The Review step of the setup wizard: what the installation is about to do (see `SetupWizard::reviewGroups()`) --}}
<div class="grid gap-4 md:grid-cols-2">
    @foreach ($groups as $group)
        <section class="rounded-lg border border-gray-200 dark:border-white/10" data-review-group="{{ $loop->index }}">
            <h3 class="flex items-center gap-2 border-b border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-950 dark:border-white/10 dark:text-white">
                <x-filament::icon :icon="$group['icon']" class="size-5 text-gray-400 dark:text-gray-500" />
                {{ $group['title'] }}
            </h3>
            <dl class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($group['items'] as $item)
                    <div class="flex items-start justify-between gap-4 px-4 py-2 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $item['label'] }}</dt>
                        @if (isset($item['enabled']))
                            <dd class="shrink-0" data-enabled="{{ $item['enabled'] ? 'true' : 'false' }}">
                                <x-filament::icon
                                    :icon="$item['enabled'] ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'"
                                    @class([
                                        'size-5',
                                        'text-success-600 dark:text-success-400' => $item['enabled'],
                                        'text-gray-400 dark:text-gray-500' => ! $item['enabled'],
                                    ])
                                />
                                <span class="sr-only">{{ $item['value'] }}</span>
                            </dd>
                        @else
                            <dd class="flex min-w-0 items-center gap-2 text-end font-medium [overflow-wrap:anywhere] text-gray-950 dark:text-white">
                                @if (filled($item['color'] ?? null))
                                    <span class="size-4 shrink-0 rounded-full border border-gray-200 dark:border-white/10" style="background-color: {{ $item['color'] }}"></span>
                                @endif
                                {{ $item['value'] }}
                            </dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
