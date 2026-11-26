{{-- The checks of the installation in the setup wizard (see `SetupWizard::installationChecks()`) --}}
@php
    use App\Enums\InstallationCheckStatus;

    $problems = collect($checks)->where('status', '!=', InstallationCheckStatus::Ok)->count();

    // The problems first, the errors before the warnings
    $checks = collect($checks)->sortBy(fn (array $check): int => match ($check['status']) {
        InstallationCheckStatus::Error => 0,
        InstallationCheckStatus::Warning => 1,
        default => 2,
    })->values()->all();
@endphp

<div class="flex flex-col gap-3">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ $problems === 0 ? __('setup.checks.all_ok') : trans_choice('setup.checks.problems', $problems, ['count' => $problems]) }}
    </p>

    <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
        @foreach ($checks as $check)
            <li class="flex items-start gap-3 px-4 py-2.5" data-check="{{ $check['key'] }}">
                <x-filament::icon
                    :icon="$check['status']->getIcon()"
                    @class([
                        'mt-0.5 size-5 shrink-0',
                        'text-success-600 dark:text-success-400' => $check['status'] === InstallationCheckStatus::Ok,
                        'text-warning-600 dark:text-warning-400' => $check['status'] === InstallationCheckStatus::Warning,
                        'text-danger-600 dark:text-danger-400' => $check['status'] === InstallationCheckStatus::Error,
                    ])
                />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $check['check'] }}</p>
                    @if ($check['details'] !== '')
                        <p class="break-words text-sm text-gray-500 dark:text-gray-400">{{ $check['details'] }}</p>
                    @endif
                </div>
                <x-filament::badge :color="$check['status']->getColor()" size="sm">
                    {{ $check['status']->getLabel() }}
                </x-filament::badge>
            </li>
        @endforeach
    </ul>
</div>
