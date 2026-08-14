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

    // Center on Rome (Italy) by default
    'default_map_center' => [41.896961975616, 12.481241781767],
];
