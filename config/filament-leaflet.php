<?php

return [
    'columns' => [
        'latitude' => 'lat',
        'longitude' => 'lng',
        'coords' => 'location',
        'radius' => 'radius',
        'title' => 'title',
        'description' => 'description',
        'bounds' => 'bounds',
        'points' => 'points',
    ],

    'sync_record_attributes' => true,

    /*
     * Where the maps open when they have nothing to show yet (e.g. a new territory), as
     * [latitude, longitude]: set `MAP_DEFAULT_CENTER` to the area of the congregation, as
     * "latitude,longitude" (e.g. "39.8017,-89.6436"). When not set, the maps open on
     * northern New Jersey (USA).
     */
    'default_map_center' => array_map(
        'floatval',
        explode(',', (string) env('MAP_DEFAULT_CENTER', '41.159063,-74.255188'), 2),
    ),
];
