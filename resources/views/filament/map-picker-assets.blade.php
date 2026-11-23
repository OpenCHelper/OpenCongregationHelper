{{--
    The script of the map picker (`dotswan/filament-map-picker`), loaded on request (see `AppServiceProvider`).

    Rendered while a map picker is rendered: Livewire adds the script to the page once, before the map is
    initialised (in the `<head>` on a page load, or before applying the update that shows the map, e.g. a modal).
--}}
@assets
    @include('filament.partials.script-loaded-on-request', [
        'src' => \Filament\Support\Facades\FilamentAsset::getScriptSrc('filament-map-picker-scripts', 'dotswan/filament-map-picker'),
    ])
@endassets
