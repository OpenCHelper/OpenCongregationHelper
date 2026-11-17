{{-- Progressive Web App: manifest, icons and service worker, shared by the home page, the layouts and the panels. --}}
<link rel="manifest" href="{{ route('web-manifest') }}">
<meta name="theme-color" content="{{ \App\Http\Controllers\WebManifestController::THEME_COLOR }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('icons/favicon-32.png') }}" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
</script>
