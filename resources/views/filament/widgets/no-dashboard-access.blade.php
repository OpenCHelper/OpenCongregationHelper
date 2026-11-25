<x-filament-widgets::widget class="fi-wi-no-dashboard-access">
    <x-filament::section
        :heading="__('filament-widgets.no_dashboard_access.heading', ['name' => filament()->getUserName(auth()->user())])"
        :icon="\Filament\Support\Icons\Heroicon::OutlinedLockClosed"
    >
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament-widgets.no_dashboard_access.description') }}
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
