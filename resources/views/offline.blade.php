{{-- Shown by the service worker (`public/sw.js`) when a page cannot be loaded offline: it is cached, so it has no external assets. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="{{ \App\Http\Controllers\WebManifestController::THEME_COLOR }}">

        <title>{{ __('pwa.offline.title') }} · {{ config('app.name') }}</title>

        <style>
            :root { color-scheme: light dark; }
            body {
                margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;
                box-sizing: border-box; background: #fafaf9; color: #44403c; text-align: center;
                font-family: Figtree, ui-sans-serif, system-ui, sans-serif;
            }
            main { max-width: 28rem; }
            .mark {
                display: inline-flex; width: 3.5rem; height: 3.5rem; align-items: center; justify-content: center;
                border-radius: 0.875rem; background: #b45309; color: #fff;
            }
            h1 { margin: 1.5rem 0 0.5rem; font-size: 1.5rem; color: #1c1917; }
            p { margin: 0 0 1.5rem; line-height: 1.6; }
            button {
                border: 0; border-radius: 0.5rem; padding: 0.625rem 1.25rem; background: #1c1917; color: #fff;
                font: inherit; font-weight: 600; cursor: pointer;
            }
            @media (prefers-color-scheme: dark) {
                body { background: #0c0a09; color: #d6d3d1; }
                h1 { color: #fff; }
                .mark { background: #f59e0b; color: #0c0a09; }
                button { background: #fff; color: #1c1917; }
            }
        </style>
    </head>
    <body>
        <main>
            <span class="mark">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                </svg>
            </span>
            <h1>{{ __('pwa.offline.title') }}</h1>
            <p>{{ __('pwa.offline.description') }}</p>
            <button type="button" onclick="window.location.reload()">{{ __('pwa.offline.retry') }}</button>
        </main>
    </body>
</html>
