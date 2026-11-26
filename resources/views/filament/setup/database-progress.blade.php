{{-- Shown while the Database step of the setup wizard creates the tables, which can take a minute --}}
<div
    wire:loading.flex
    wire:target="callSchemaComponentMethod"
    class="items-center gap-3 rounded-lg bg-primary-50 px-4 py-3 text-sm font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-300"
>
    <x-filament::loading-indicator class="size-5" />
    {{ __('setup.database.running') }}
</div>
