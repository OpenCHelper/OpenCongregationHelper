{{--
    The script of the maps of `eduardoribeirodev/filament-leaflet`, loaded on request (see `AppServiceProvider`).

    Rendered while one of these maps is rendered: Livewire adds the script to the page once, before the map is
    initialised (in the `<head>` on a page load, or before applying the update that shows the map).

    The script defines the maps' Alpine components on `livewire:init`. Loaded by an update (e.g. the lazy dashboard
    widget), Livewire has already started and that event will not come again: its listeners are then run at once.
--}}
@assets
    <script>
        if (window.Livewire) {
            window.filamentLeafletAddEventListener = document.addEventListener;

            document.addEventListener = function (type, listener, options) {
                if (type === 'livewire:init') {
                    listener(new CustomEvent(type));

                    return;
                }

                return window.filamentLeafletAddEventListener.call(this, type, listener, options);
            };
        }
    </script>
    <script src="{{ \Filament\Support\Facades\FilamentAsset::getScriptSrc('leaflet-map', 'eduardoribeirodev/filament-leaflet') }}"></script>
    <script>
        if (window.filamentLeafletAddEventListener) {
            document.addEventListener = window.filamentLeafletAddEventListener;

            delete window.filamentLeafletAddEventListener;
        }
    </script>
@endassets
