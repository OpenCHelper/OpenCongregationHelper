<?php

return [
    'territory' => [
        'update_existing' => 'Update existing records',
        'image_import' => [
            'label' => 'How do you want to store the images?',
            'store_link' => 'Keep link',
            'download_image' => 'Save copy',
        ],
        'columns' => [
            'name' => 'Name',
            'description' => 'Description',
            'image_path' => 'Image',
            'image_path_helper' => 'Currently, only absolute URLs are supported.',
            'geojson' => 'GeoJSON',
            'notes' => 'Notes',
            'is_commercial' => 'Commercial?',
            'territory_zone' => 'Zone',
        ],
        'notifications' => [
            'completed' => [
                'body' => 'Your territory import has completed: :count row imported.|Your territory import has completed: :count rows imported.',
                'failed_rows' => ':count row failed to import.|:count rows failed to import.',
            ],
        ],
        'errors' => [
            'duplicate' => 'A territory named ":name" already exists in the zone ":zone".',
            'update_unauthorized' => 'You are not allowed to update the territory ":name" of the zone ":zone".',
            'zone_create_unauthorized' => 'The zone ":zone" does not exist and you are not allowed to create it.',
            'image_unsupported_scheme' => 'Unsupported URI scheme ":scheme" for the image ":url".',
            'image_larger_than' => 'the image is larger than :size',
            'image_download_failed' => 'Unable to download the image ":url": :reason',
            'image_http_error' => 'Unable to download the image ":url": HTTP status :status.',
            'image_too_large' => 'The image ":url" is larger than :size.',
            'image_invalid_mime_type' => 'Invalid MIME type ":mime_type" for the image ":url".',
            'image_unresolvable_host' => 'Unable to resolve the host of the image ":url".',
            'image_private_host' => 'The image ":url" points to a private or reserved address.',
        ],
    ],
    'poster' => [
        'columns' => [
            'code' => 'Code',
            'title' => 'Title',
            'storage' => 'Storage place',
            'quantity' => 'Copies',
            'notes' => 'Notes',
        ],
        'options' => [
            'locale' => 'Language of the titles',
            'locale_helper' => 'The language the titles in the file are written in: they are saved as its translation, keeping the other languages.',
        ],
        'examples' => [
            'title' => 'Enjoy life forever!',
            'storage' => 'Kingdom Hall',
            'other_storage' => 'Storage box',
            'notes' => 'New edition',
        ],
        'notifications' => [
            'completed' => [
                'body' => 'Your poster import has completed: :count row imported.|Your poster import has completed: :count rows imported.',
                'failed_rows' => ':count row failed to import.|:count rows failed to import.',
            ],
        ],
        'errors' => [
            'unauthorized' => 'You are not allowed to import the poster ":title".',
            'storage_create_unauthorized' => 'The storage place ":storage" does not exist and you are not allowed to create it.',
            'quantity_without_storage' => 'The copies of the poster ":title" have no storage place.',
        ],
    ],
];
