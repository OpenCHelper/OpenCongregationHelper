{{--
    A Leaflet plugin's script loaded on request, inside the `@assets` block of the view that loads it (see
    `AppServiceProvider::loadMapScriptOnRequest()`).

    The map plugins define their Alpine components on `livewire:init`. Loaded by an update (e.g. a lazy widget or a
    modal), Livewire has already started and that event will not come again: its listeners are then run at once.
    Livewire adds the scripts of an update one after the other, waiting for each one to load.

    @param  string  $src  The URL of the script.
--}}
<script>
    if (window.Livewire) {
        window.mapScriptAddEventListener = document.addEventListener;

        document.addEventListener = function (type, listener, options) {
            if (type === 'livewire:init') {
                listener(new CustomEvent(type));

                return;
            }

            return window.mapScriptAddEventListener.call(this, type, listener, options);
        };
    }
</script>
<script src="{{ $src }}"></script>
<script>
    if (window.mapScriptAddEventListener) {
        document.addEventListener = window.mapScriptAddEventListener;

        delete window.mapScriptAddEventListener;
    }
</script>
