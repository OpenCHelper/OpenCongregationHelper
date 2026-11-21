<?php

return [
    'action' => [
        'label' => 'S-13',
        'modal_heading' => 'Territory Assignment Record (S-13)',
        'modal_description' => 'Download the S-13 of a service year, filled with the assignments of the territories on the official form.',
        'submit' => 'Download',
    ],
    'fields' => [
        'service_year' => [
            'label' => 'Service year',
            'helper' => 'A service year runs from September 1 to August 31.',
            'option' => ':year (September :from – August :to)',
        ],
        'template' => [
            'label' => 'Official S-13 form',
            'replace_label' => 'Replace the official S-13 form',
            'helper' => 'The fillable PDF of the S-13 (Territory Assignment Record), downloaded from JW Hub, in the language of the congregation.',
            'invalid' => 'This file is not a PDF that can be filled: upload the S-13 downloaded from JW Hub.',
        ],
    ],
    'template' => [
        'missing_heading' => 'The official S-13 form was not uploaded yet',
        'missing_admin' => 'Upload the S-13 downloaded from JW Hub: it is kept for the next downloads.',
        'missing_user' => 'Ask a super administrator to upload the S-13 downloaded from JW Hub.',
    ],
    'structure' => [
        'stored_heading' => 'The uploaded S-13 form differs from the one the app was made for',
        'uploaded_heading' => 'This S-13 form differs from the one the app was made for',
        'description' => 'It is probably a new edition of the form: the record could be written in the wrong places. Please report it, attaching the form, so that the app is updated. Differences:',
        'report' => 'Report it on GitHub',
        'accept' => 'Use this form anyway',
        'accept_required' => 'Confirm that this form must be used although it differs from the expected one.',
        'pages' => 'Pages: :actual instead of :expected',
        'size' => 'Page size: :actual instead of :expected',
        'missing_fields' => 'Missing fields: :fields',
        'added_fields' => 'New fields: :fields',
        'grid' => 'The lines of the table are in different places',
        'and_more' => 'and :count more',
    ],
];
