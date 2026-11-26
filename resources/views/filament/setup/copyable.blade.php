{{-- A value to copy, e.g. a command or a URL, in the Background tasks step of the setup wizard --}}
<div x-data="{ copied: false }" class="flex items-start gap-2">
    <pre class="min-w-0 flex-1 overflow-x-auto rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-950 dark:bg-white/5 dark:text-white"><code>{{ $value }}</code></pre>
    <x-filament::icon-button
        icon="heroicon-o-clipboard-document"
        color="gray"
        :label="__('setup.tasks.copy')"
        x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($value) }}); copied = true; setTimeout(() => copied = false, 2000)"
        x-bind:class="copied && 'text-success-600'"
    />
</div>
