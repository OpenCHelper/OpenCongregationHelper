{{-- How to run the scheduler and the queue without a shell, in the Background tasks step of the setup wizard --}}
<div class="flex flex-col gap-3 text-sm text-gray-700 dark:text-gray-300">
    <p>{{ __('setup.tasks.service_intro') }}</p>
    @include('filament.setup.copyable', ['value' => $schedulerUrl])

    @if ($queueUrl !== null)
        @include('filament.setup.copyable', ['value' => $queueUrl])
    @else
        <p>{{ __('setup.tasks.sync_note') }}</p>
    @endif

    @if (filled($token))
        <p>{{ __('setup.tasks.token_intro') }}</p>
        @include('filament.setup.copyable', ['value' => 'X-Cron-Token: '.$token])
    @else
        <p>{{ __('setup.tasks.token_missing') }}</p>
    @endif
</div>
