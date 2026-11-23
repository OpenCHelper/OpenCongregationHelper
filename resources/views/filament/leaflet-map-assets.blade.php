{{--
    The script of the maps of `eduardoribeirodev/filament-leaflet`, loaded on request (see `AppServiceProvider`).

    Rendered while one of these maps is rendered: Livewire adds the script to the page once, before the map is
    initialised (in the `<head>` on a page load, or before applying the update that shows the map).
--}}
@assets
    @include('filament.partials.script-loaded-on-request', [
        'src' => \Filament\Support\Facades\FilamentAsset::getScriptSrc('leaflet-map', 'eduardoribeirodev/filament-leaflet'),
    ])
@endassets
