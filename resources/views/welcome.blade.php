@php
    $appName = config('app.name');
    $appVersion = config('app.version');
    $repositoryUrl = config('app.repository_url');

    /** @var array<string, string> $features Feature translation key => Heroicon component name */
    $features = [
        'territories' => 'heroicon-o-map',
        'assignments' => 'heroicon-o-clipboard-document-check',
        'campaigns' => 'heroicon-o-calendar-days',
        'overview' => 'heroicon-o-chart-bar',
        'sharing' => 'heroicon-o-share',
        'notifications' => 'heroicon-o-bell',
        'publications' => 'heroicon-o-newspaper',
        'public_witnessing' => 'heroicon-o-photo',
        'access' => 'heroicon-o-shield-check',
    ];

    $primaryActionUrl = auth()->check() ? route('filament.app.pages.dashboard') : route('filament.app.auth.login');
    $primaryActionLabel = auth()->check() ? __('welcome.hero.primary_action_auth') : __('welcome.hero.primary_action_guest');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ __('welcome.meta_description') }}">
        <x-pwa-head />

        <title>{{ $appName }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/guest.css'])
    </head>
    <body class="min-h-screen bg-stone-50 font-[Figtree,ui-sans-serif,system-ui,sans-serif] text-stone-700 antialiased selection:bg-amber-200 selection:text-stone-900 dark:bg-stone-950 dark:text-stone-300 dark:selection:bg-amber-500/40 dark:selection:text-white">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-[32rem] bg-linear-to-b from-amber-100/70 to-transparent dark:from-amber-500/10"></div>

        <div class="relative mx-auto flex min-h-screen max-w-6xl flex-col px-6 lg:px-8">
            <header class="flex flex-wrap items-center justify-between gap-4 py-6">
                <a href="{{ url('/') }}" class="flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-amber-700 text-white shadow-sm dark:bg-amber-500 dark:text-stone-950">
                        <x-heroicon-o-map-pin class="size-5" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight text-stone-900 dark:text-white">{{ $appName }}</span>
                </a>

                <nav class="flex items-center gap-1 text-sm font-medium">
                    <a href="{{ route('filament.knowledge-base.home') }}" class="rounded-lg px-3 py-2 text-stone-600 transition hover:bg-stone-200/60 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 dark:text-stone-400 dark:hover:bg-white/5 dark:hover:text-white">
                        {{ __('welcome.nav.guide') }}
                    </a>
                    <a href="{{ $repositoryUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-lg px-3 py-2 text-stone-600 transition hover:bg-stone-200/60 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 dark:text-stone-400 dark:hover:bg-white/5 dark:hover:text-white">
                        {{ __('welcome.nav.repository') }}
                    </a>
                    @auth
                        <a href="{{ route('filament.app.pages.dashboard') }}" class="ml-2 rounded-lg bg-stone-900 px-4 py-2 text-white shadow-sm transition hover:bg-stone-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 dark:bg-white dark:text-stone-900 dark:hover:bg-stone-200 dark:focus-visible:ring-offset-stone-950">
                            {{ __('welcome.nav.open_app') }}
                        </a>
                    @else
                        <a href="{{ route('filament.app.auth.login') }}" class="ml-2 rounded-lg bg-stone-900 px-4 py-2 text-white shadow-sm transition hover:bg-stone-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 dark:bg-white dark:text-stone-900 dark:hover:bg-stone-200 dark:focus-visible:ring-offset-stone-950">
                            {{ __('welcome.nav.login') }}
                        </a>
                    @endauth
                </nav>
            </header>

            <main class="flex-1">
                <section class="grid items-center gap-12 py-16 sm:py-24 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
                    <div>
                        <p class="inline-flex flex-wrap items-center gap-x-2 gap-y-1 rounded-full bg-white/80 px-3 py-1 text-xs font-medium text-amber-800 ring-1 ring-amber-200 dark:bg-white/5 dark:text-amber-300 dark:ring-amber-400/20">
                            <span>{{ __('welcome.hero.eyebrow') }}</span>
                            <span class="text-amber-300 dark:text-amber-400/40" aria-hidden="true">&middot;</span>
                            <span class="font-mono">{{ $appVersion }}</span>
                        </p>

                        <h1 class="mt-6 text-4xl font-bold tracking-tight text-balance text-stone-900 sm:text-5xl dark:text-white">
                            {{ __('welcome.hero.title') }}
                        </h1>

                        <p class="mt-6 text-lg/8 text-pretty text-stone-600 dark:text-stone-400">
                            {{ __('welcome.hero.description', ['app' => $appName]) }}
                        </p>

                        <div class="mt-10 flex flex-wrap items-center gap-3">
                            <a href="{{ $primaryActionUrl }}" class="inline-flex items-center gap-2 rounded-lg bg-amber-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 dark:bg-amber-500 dark:text-stone-950 dark:hover:bg-amber-400 dark:focus-visible:ring-offset-stone-950">
                                {{ $primaryActionLabel }}
                                <x-heroicon-o-arrow-right class="size-4" />
                            </a>
                            <a href="{{ route('filament.knowledge-base.home') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-stone-900 shadow-sm ring-1 ring-stone-200 transition hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:hover:bg-white/10">
                                <x-heroicon-o-book-open class="size-4" />
                                {{ __('welcome.hero.secondary_action') }}
                            </a>
                        </div>
                    </div>

                    <div class="hidden lg:block" aria-hidden="true">
                        <div class="rounded-3xl bg-white p-3 shadow-xl shadow-amber-900/5 ring-1 ring-stone-200/80 dark:bg-white/[0.03] dark:shadow-none dark:ring-white/10">
                            <svg viewBox="0 0 400 320" class="block h-auto w-full rounded-2xl" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect width="400" height="320" class="fill-stone-100 dark:fill-stone-900" />

                                <g class="stroke-white dark:stroke-stone-800" stroke-linecap="round">
                                    <path d="M-10 98C110 76 220 128 410 86" stroke-width="14" />
                                    <path d="M150 -10L172 330" stroke-width="12" />
                                    <path d="M-10 236L410 262" stroke-width="12" />
                                    <path d="M300 -10C278 104 326 214 306 330" stroke-width="10" />
                                </g>

                                <g stroke-width="2" stroke-linejoin="round">
                                    <path d="M18 18H128L134 74L20 86Z" class="fill-stone-200/70 stroke-stone-300 dark:fill-stone-800 dark:stroke-stone-700" stroke-dasharray="5 5" />
                                    <path d="M180 16H282L276 88L188 104Z" class="fill-stone-200/70 stroke-stone-300 dark:fill-stone-800 dark:stroke-stone-700" stroke-dasharray="5 5" />
                                    <path d="M22 114L130 104L142 220L28 222Z" class="fill-amber-100 stroke-amber-500 dark:fill-amber-500/15 dark:stroke-amber-500/70" stroke-dasharray="5 5" />
                                    <path d="M188 120L282 108L294 236L196 240Z" class="fill-amber-200 stroke-amber-600 dark:fill-amber-500/30 dark:stroke-amber-400" />
                                    <path d="M324 100L386 94L388 240L330 244Z" class="fill-stone-200/70 stroke-stone-300 dark:fill-stone-800 dark:stroke-stone-700" stroke-dasharray="5 5" />
                                    <path d="M24 262L144 270L146 302L24 302Z" class="fill-amber-100 stroke-amber-500 dark:fill-amber-500/15 dark:stroke-amber-500/70" stroke-dasharray="5 5" />
                                    <path d="M196 274L292 280L290 302L196 302Z" class="fill-stone-200/70 stroke-stone-300 dark:fill-stone-800 dark:stroke-stone-700" stroke-dasharray="5 5" />
                                </g>

                                <g transform="translate(241 150)">
                                    <ellipse cx="0" cy="30" rx="10" ry="3" class="fill-stone-900/15 dark:fill-black/40" />
                                    <path d="M0 30C0 30 -18 10 -18 -2A18 18 0 0 1 18 -2C18 10 0 30 0 30Z" class="fill-amber-700 dark:fill-amber-500" />
                                    <circle cx="0" cy="-2" r="6.5" class="fill-white dark:fill-stone-950" />
                                </g>
                            </svg>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="features-heading" class="border-t border-stone-200 py-16 sm:py-20 dark:border-white/10">
                    <div class="max-w-2xl">
                        <h2 id="features-heading" class="text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">
                            {{ __('welcome.features.heading') }}
                        </h2>
                        <p class="mt-3 text-base/7 text-stone-600 dark:text-stone-400">
                            {{ __('welcome.features.description') }}
                        </p>
                    </div>

                    <ul role="list" class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($features as $feature => $icon)
                            <li class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200/80 dark:bg-white/[0.03] dark:ring-white/10">
                                <span class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20">
                                    <x-dynamic-component :component="$icon" class="size-6" />
                                </span>
                                <h3 class="mt-5 text-base font-semibold text-stone-900 dark:text-white">
                                    {{ __("welcome.features.{$feature}.title") }}
                                </h3>
                                <p class="mt-2 text-sm/6 text-stone-600 dark:text-stone-400">
                                    {{ __("welcome.features.{$feature}.description") }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section aria-labelledby="open-source-heading" class="pb-20">
                    <div class="flex flex-col gap-6 rounded-2xl bg-stone-900 px-6 py-10 sm:px-10 lg:flex-row lg:items-center lg:justify-between dark:bg-white/5 dark:ring-1 dark:ring-white/10">
                        <div class="max-w-2xl">
                            <h2 id="open-source-heading" class="text-2xl font-semibold tracking-tight text-white">
                                {{ __('welcome.open_source.heading') }}
                            </h2>
                            <p class="mt-3 text-base/7 text-stone-300">
                                {{ __('welcome.open_source.description') }}
                            </p>
                        </div>
                        <a href="{{ $repositoryUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex shrink-0 items-center gap-2 self-start rounded-lg bg-white px-5 py-3 text-sm font-semibold text-stone-900 shadow-sm transition hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 focus-visible:ring-offset-stone-900 lg:self-auto">
                            <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0 1 12 6.844a9.59 9.59 0 0 1 2.504.337c1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.02 10.02 0 0 0 22 12.017C22 6.484 17.522 2 12 2Z" />
                            </svg>
                            {{ __('welcome.open_source.action') }}
                        </a>
                    </div>
                </section>
            </main>

            <footer class="flex flex-col gap-2 border-t border-stone-200 py-8 text-sm text-stone-500 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                <p>&copy; {{ now()->year }} {{ $appName }} &middot; {{ __('welcome.footer.license') }}</p>
                <p class="flex items-center gap-4">
                    <span>{{ __('welcome.footer.version') }} <span class="font-mono text-stone-700 dark:text-stone-300">{{ $appVersion }}</span></span>
                    <a href="{{ $repositoryUrl }}" target="_blank" rel="noopener noreferrer" class="underline decoration-stone-300 underline-offset-4 transition hover:text-stone-900 hover:decoration-stone-500 dark:decoration-stone-700 dark:hover:text-white">
                        {{ __('welcome.nav.repository') }}
                    </a>
                </p>
            </footer>
        </div>
    </body>
</html>
