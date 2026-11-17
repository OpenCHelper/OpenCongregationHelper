{{--
    Versions of the app (and, outside production, of its stack), shown in the modal opened from the user menu.

    @var array<\Awcodes\Versions\Providers\Contracts\VersionProvider> $versions
--}}
<dl class="app-version-info divide-y divide-gray-950/5 text-sm dark:divide-white/10">
    @foreach ($versions as $version)
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
            <dt class="font-medium text-gray-950 dark:text-white">{{ $version->getName() }}</dt>
            <dd class="break-all font-mono text-gray-600 dark:text-gray-400">{{ str($version->getVersion())->ltrim('v') }}</dd>
        </div>
    @endforeach
</dl>
