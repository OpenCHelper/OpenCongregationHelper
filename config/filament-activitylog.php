<?php

return [
    'resources' => [
        'label'                  => 'Activity Log',
        'plural_label'           => 'Activity Logs',
        'navigation_item'        => true,
        'navigation_group'       => 'Amministrazione',
        'navigation_icon'        => 'heroicon-o-cursor-arrow-ripple',
        'navigation_sort'        => 100,
        'default_sort_column'    => 'id',
        'default_sort_direction' => 'desc',
        'navigation_count_badge' => true,
        'resource'               => \App\Filament\Resources\ActivitylogResource::class,
    ],
    'datetime_format' => 'd/m/Y H:i:s',
];
