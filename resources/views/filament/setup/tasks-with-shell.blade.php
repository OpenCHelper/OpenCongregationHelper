{{-- How to run the scheduler and the queue with a shell, in the Background tasks step of the setup wizard --}}
<div class="flex flex-col gap-3 text-sm text-gray-700 dark:text-gray-300">
    <p>{{ __('setup.tasks.cron_intro') }}</p>
    @include('filament.setup.copyable', ['value' => '* * * * * '.$scheduler])

    @if ($worker !== null)
        <p>{{ __('setup.tasks.worker_intro') }}</p>
        @include('filament.setup.copyable', ['value' => '* * * * * '.$worker])
    @else
        <p>{{ __('setup.tasks.sync_note') }}</p>
    @endif
</div>
