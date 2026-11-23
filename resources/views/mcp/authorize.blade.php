{{--
    Where the user approves an AI assistant that asks to connect to the MCP server (OAuth authorization of Passport,
    see `AppServiceProvider::configureAiAssistants()`). Self-contained, as the pages outside the panel (e.g. `offline`).
--}}
@php
    /** @var \Laravel\Passport\Client $client */
    $redirectUri = request()->query('redirect_uri', $client->redirect_uris[0] ?? null);
    $redirectHost = is_string($redirectUri) ? (parse_url($redirectUri, PHP_URL_HOST) ?: $redirectUri) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="{{ \App\Http\Controllers\WebManifestController::themeColor() }}">

        <title>{{ __('ai-assistants.authorize.title', ['name' => $client->name]) }} · {{ config('app.name') }}</title>

        <style>
            :root { color-scheme: light dark; }
            body {
                margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;
                box-sizing: border-box; background: #fafaf9; color: #44403c;
                font-family: Figtree, ui-sans-serif, system-ui, sans-serif; line-height: 1.6;
            }
            main {
                width: 100%; max-width: 30rem; box-sizing: border-box; padding: 2rem; border-radius: 1rem;
                background: #fff; box-shadow: 0 1px 3px rgb(0 0 0 / 0.08), 0 0 0 1px rgb(0 0 0 / 0.05);
            }
            .mark {
                display: flex; width: 3.5rem; height: 3.5rem; margin: 0 auto; align-items: center; justify-content: center;
                border-radius: 0.875rem; background: #b45309; color: #fff;
            }
            h1 { margin: 1.25rem 0 0.25rem; font-size: 1.375rem; line-height: 1.3; color: #1c1917; text-align: center; overflow-wrap: anywhere; }
            .account { margin: 0 0 1.5rem; text-align: center; font-size: 0.875rem; }
            h2 { margin: 0 0 0.5rem; font-size: 0.875rem; color: #1c1917; }
            ul { margin: 0 0 1.25rem; padding-left: 1.25rem; font-size: 0.875rem; }
            li + li { margin-top: 0.25rem; }
            .notice {
                margin: 0 0 1.5rem; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem;
                background: #fffbeb; color: #78350f; overflow-wrap: anywhere;
            }
            .actions { display: flex; gap: 0.75rem; }
            .actions form { flex: 1; }
            button {
                width: 100%; border: 0; border-radius: 0.5rem; padding: 0.625rem 1rem; font: inherit; font-weight: 600;
                cursor: pointer; background: #1c1917; color: #fff;
            }
            button.secondary { background: transparent; color: #44403c; box-shadow: inset 0 0 0 1px #d6d3d1; }
            @media (prefers-color-scheme: dark) {
                body { background: #0c0a09; color: #d6d3d1; }
                main { background: #1c1917; box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1); }
                h1, h2 { color: #fff; }
                .mark { background: #f59e0b; color: #0c0a09; }
                .notice { background: rgb(245 158 11 / 0.12); color: #fcd34d; }
                button { background: #fff; color: #1c1917; }
                button.secondary { color: #d6d3d1; box-shadow: inset 0 0 0 1px #44403c; }
            }
        </style>
    </head>
    <body>
        <main>
            <span class="mark">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                </svg>
            </span>

            <h1>{{ __('ai-assistants.authorize.title', ['name' => $client->name]) }}</h1>
            <p class="account">{{ __('ai-assistants.authorize.account', ['name' => $user->getFullName(), 'app' => config('app.name')]) }}</p>

            <h2>{{ __('ai-assistants.authorize.can_heading') }}</h2>
            <ul>
                <li>{{ __('ai-assistants.authorize.can_read') }}</li>
                <li>{{ __('ai-assistants.authorize.can_change') }}</li>
                <li>{{ __('ai-assistants.authorize.can_revoke') }}</li>
            </ul>

            @if ($redirectHost)
                <p class="notice">{{ __('ai-assistants.authorize.redirect_notice', ['host' => $redirectHost]) }}</p>
            @endif

            <div class="actions">
                <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="state" value="">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="secondary">{{ __('ai-assistants.authorize.deny') }}</button>
                </form>

                <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                    @csrf
                    <input type="hidden" name="state" value="">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit">{{ __('ai-assistants.authorize.approve') }}</button>
                </form>
            </div>
        </main>
    </body>
</html>
