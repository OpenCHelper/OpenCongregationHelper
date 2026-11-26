{{-- Reminds the root user to complete the first-run setup, on every page until it is completed or skipped (see `SetupWizard`) --}}
<div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl bg-primary-50 px-4 py-3 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:ring-primary-400/30">
    <x-filament::icon icon="heroicon-o-rocket-launch" class="size-5 shrink-0 text-primary-600 dark:text-primary-400" />
    <p class="min-w-0 flex-1 text-sm text-gray-950 dark:text-white">{{ __('setup.banner.text') }}</p>
    <x-filament::button tag="a" :href="\App\Filament\Pages\SetupWizard::getUrl()" size="sm">
        {{ __('setup.banner.action') }}
    </x-filament::button>
</div>
