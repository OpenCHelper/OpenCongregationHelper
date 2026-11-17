{{--
    Versions of the app (and, outside production, of its stack), shown in the modal opened from the user menu.

    @var array<string, string> $versions Version by name of the component
--}}
<dl class="app-version-info divide-y divide-gray-950/5 text-sm dark:divide-white/10">
    @foreach ($versions as $name => $version)
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
            <dt class="font-medium text-gray-950 dark:text-white">{{ $name }}</dt>
            <dd class="break-all font-mono text-gray-600 dark:text-gray-400">{{ $version }}</dd>
        </div>
    @endforeach
</dl>
