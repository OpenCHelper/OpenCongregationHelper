<?php

return [
    'notifications' => [
        'completed' => [
            'body' => 'The export has completed: :count row exported.|The export has completed: :count rows exported.',
            'failed_rows' => ':count row failed to export.|:count rows failed to export.',
        ],
    ],
    'options' => [
        'locale_titles' => 'Language of the titles',
        'locale_helper' => 'The titles are exported in this language (or in another one, when they have no translation in it).',
    ],
];
