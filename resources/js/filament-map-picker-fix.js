/**
 * Shows the map of the map picker (`dotswan/filament-map-picker`) as soon as part of it is visible.
 *
 * The picker removes its Leaflet map when the map is out of view, and creates it again only when the map is
 * **fully** visible (an `IntersectionObserver` with `threshold: 1.0`). A map in an inactive Filament tab is out
 * of view, so after switching to its tab the map stays a grey box until it is scrolled fully into view, and a
 * map taller than the viewport never shows.
 *
 * This wraps the picker's `attach()` to also observe the map with a low threshold: as soon as part of the map
 * is visible, the map is created again if the picker removed it, or resized to its container otherwise (the
 * container can change size while it is hidden).
 *
 * @see {@link https://github.com/dotswan/filament-map-picker/blob/master/resources/js/index.js|Map picker script (`attach()`)}
 */
document.addEventListener('alpine:init', () => {
    // The picker defines `window.mapPicker` on `livewire:init`, which Livewire dispatches before starting Alpine.
    const createMapPicker = window.mapPicker;

    if (typeof createMapPicker !== 'function') {
        return;
    }

    window.mapPicker = ($wire, config) => {
        const picker = createMapPicker($wire, config);
        const attach = picker.attach;

        if (typeof attach !== 'function') {
            return picker;
        }

        picker.attach = function (el, refs = null) {
            attach.call(this, el, refs);

            // Not 0: an element clipped by a hidden tab can count as intersecting with an empty area, and the
            // callback only runs when a threshold is crossed.
            const observer = new IntersectionObserver((entries) => {
                const latestEntry = entries[entries.length - 1];

                if (latestEntry.intersectionRatio <= 0) {
                    return;
                }

                if (!this.map) {
                    this.createMap(el);

                    return;
                }

                this.map.invalidateSize();
            }, { threshold: 0.01 });

            observer.observe(el);
        };

        return picker;
    };
});
