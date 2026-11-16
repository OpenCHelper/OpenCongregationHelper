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
     * [latitude, longitude]: set it to the area of the congregation. Rome (Italy) by default.
     */
    'default_map_center' => [
        (float) env('MAP_DEFAULT_LATITUDE', 41.896961975616),
        (float) env('MAP_DEFAULT_LONGITUDE', 12.481241781767),
    ],
];
