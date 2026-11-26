{{-- The keys created by the Configuration step of the setup wizard: those that exist, and those created on going on --}}
<ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
    @foreach ($keys as $key)
        <li class="flex items-center gap-3 px-4 py-2.5" data-key="{{ $key['key'] }}" data-status="{{ $key['status'] }}">
            <x-filament::icon
                :icon="match ($key['status']) {
                    'set' => 'heroicon-o-check-circle',
                    'created' => 'heroicon-o-plus-circle',
                    default => 'heroicon-o-exclamation-triangle',
                }"
                @class([
                    'size-5 shrink-0',
                    'text-success-600 dark:text-success-400' => $key['status'] === 'set',
                    'text-info-600 dark:text-info-400' => $key['status'] === 'created',
                    'text-warning-600 dark:text-warning-400' => $key['status'] === 'unavailable',
                ])
            />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $key['label'] }}</p>
                @if (filled($key['details'] ?? null))
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $key['details'] }}</p>
                @endif
            </div>
            <x-filament::badge :color="match ($key['status']) { 'set' => 'success', 'created' => 'info', default => 'warning' }" size="sm">
                {{ __("setup.environment.key_statuses.{$key['status']}") }}
            </x-filament::badge>
        </li>
    @endforeach
</ul>
