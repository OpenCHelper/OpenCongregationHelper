{{--
    Versions of the app and, outside production, of its stack and the details of its build, shown in the modal opened
    from the user menu.

    @var array<string, string> $versions Version by name of the component
    @var array{branch: ?string, commit: ?string, date: ?string}|null $build Details of the build (`null` in production)
--}}
@php
    $unknown = __('filament-resources.version_info.unknown');

    $buildDetails = $build === null ? [] : [
        __('filament-resources.version_info.branch') => [$build['branch'], null],
        // The short hash is enough to tell the builds apart, the full one is in the tooltip
        __('filament-resources.version_info.commit') => [filled($build['commit']) ? substr($build['commit'], 0, 7) : null, $build['commit']],
        __('filament-resources.version_info.date') => [$build['date'], null],
    ];
@endphp

<div class="app-version-info flex flex-col gap-y-6 text-sm">
    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ __('filament-resources.version_info.versions') }}
        </h3>

        <dl class="divide-y divide-gray-950/5 dark:divide-white/10">
            @foreach ($versions as $name => $version)
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                    <dt class="font-medium text-gray-950 dark:text-white">{{ $name }}</dt>
                    <dd class="break-all font-mono text-gray-600 dark:text-gray-400">{{ $version }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    @if ($buildDetails !== [])
        <section class="app-build-info">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('filament-resources.version_info.build') }}
            </h3>

            <dl class="divide-y divide-gray-950/5 dark:divide-white/10">
                @foreach ($buildDetails as $label => [$value, $title])
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                        <dt class="font-medium text-gray-950 dark:text-white">{{ $label }}</dt>
                        @if (filled($value))
                            <dd class="break-all font-mono text-gray-600 dark:text-gray-400" @if ($title) title="{{ $title }}" @endif>{{ $value }}</dd>
                        @else
                            <dd class="italic text-gray-400 dark:text-gray-500">{{ $unknown }}</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </section>
    @endif
</div>
