{{-- What the setup wizard did, once the app is installed (see `SetupWizard::complete()`) --}}
<ul class="flex flex-col gap-2.5">
    @foreach ($items as $item)
        <li class="flex items-start gap-3 text-sm" data-status="{{ $item['status'] }}">
            <x-filament::icon
                :icon="match ($item['status']) {
                    'done' => 'heroicon-o-check-circle',
                    'warning' => 'heroicon-o-exclamation-triangle',
                    default => 'heroicon-o-arrow-right-circle',
                }"
                @class([
                    'size-5 shrink-0',
                    'text-success-600 dark:text-success-400' => $item['status'] === 'done',
                    'text-warning-600 dark:text-warning-400' => $item['status'] === 'warning',
                    'text-primary-600 dark:text-primary-400' => $item['status'] === 'todo',
                ])
            />
            <span @class([
                'text-gray-700 dark:text-gray-300',
                'font-medium text-gray-950 dark:text-white' => $item['status'] !== 'done',
            ])>{{ $item['text'] }}</span>
        </li>
    @endforeach
</ul>
