<?php

return [
    'navigation_label' => 'Settings',
    'default' => 'Default: :value.',

    'units' => [
        'days_before_end' => 'days before the end',
        'days_after_assignment' => 'days after the assignment',
        'assignments' => 'assignments',
        'months' => 'months',
    ],

    'stages' => [
        'active_note' => 'until it is expiring',
        'terminated_note' => 'from the end date',
        'in_progress' => 'In progress',
        'in_progress_note' => 'from the day of the assignment',
        'end_date' => 'End date',
        'assigned' => 'Assigned',
        'low_frequency' => 'Low',
        'low_frequency_note' => 'fewer assignments, or none',
    ],

    'pages' => [
        'general' => 'General',
        'territories' => 'Territories',
        'campaigns' => 'Campaigns',
    ],

    'sections' => [
        'campaign_expiry' => 'Campaign expiry',
        'campaign_expiry_description' => 'As its end date gets closer, an active campaign becomes expiring and then almost expired.',
        'appearance' => 'Appearance',
        'congregation' => 'Congregation',
        'maps' => 'Maps',
        'shared_pages_privacy' => 'Privacy of shared pages',
        'shared_pages_privacy_description' => 'What the public page of a shared assignment shows to anyone who has its link.',
        'stale_assignments' => 'Assignments out for too long',
        'stale_assignments_description' => 'Assignments still in progress after these days are out for too long: once a week, their assignees are reminded to return them.',
        'assignment_frequency' => 'Assignment frequency',
        'assignment_frequency_description' => 'How frequently each territory is assigned, as coloured on the dashboard map: by the number of its assignments in the last months.',
    ],

    'fields' => [
        'kingdom_hall_address' => [
            'label' => 'Kingdom Hall address',
            'placeholder' => 'Street, number, postcode and city',
        ],
        'logo' => [
            'label' => 'Logo',
            'helper' => 'Shown in the top bar instead of the name. At most 2 MB.',
        ],
        'primary_color' => [
            'label' => 'Primary colour',
            'helper' => 'Colour of the buttons, links and selected items, for the users who did not choose their own in their UI preferences.',
        ],
        'map_default_center' => [
            'label' => 'Default map centre',
            'helper' => 'Where the maps open while no territory has a map yet, as "latitude, longitude".',
            'invalid' => 'Write the coordinates as "latitude, longitude" (e.g. 41.159063, -74.255188).',
        ],
        'shared_pii' => [
            'hide' => 'Hide personal data',
            'hide_description' => 'Only the territory, its map and the dates: the assignee and the notes stay inside the app.',
            'show' => 'Show personal data',
            'show_description' => 'Also the name of the assignee and the notes of the territory, to anyone who has the link. The links never expire and can be forwarded.',
            'recommended' => 'Recommended',
            'confirm_heading' => 'Show personal data on shared pages?',
            'consequences' => [
                'Anyone who has the link of a shared assignment will see the name of the assignee and the notes of the territory, which may describe householders.',
                'The shared links never expire and can be forwarded, e.g. in a group chat.',
                'Do it only if the congregation has agreed to it and the notes hold no personal data.',
            ],
            'understood' => 'I understand that anyone with a shared link will see this data, and the congregation has agreed to it.',
            'understood_required' => 'Confirm that you understand what showing the personal data means.',
            'confirm' => 'Show personal data',
        ],
        'stale_after_days' => [
            'label' => 'Out for too long',
        ],
        'dead_after_days' => [
            'label' => 'Lost',
        ],
        'stale_check_day' => [
            'label' => 'Day of the reminders',
        ],
        'stale_check_time' => [
            'label' => 'Time of the reminders',
            'helper' => 'In the timezone of the application (`APP_TIMEZONE`). The reminders are sent only if the scheduler is running at that time, as a cron job or as a long-running process (e.g. with Supervisor): see the setup guide.',
        ],
        'frequency_period_months' => [
            'label' => 'Months counted',
            'helper' => 'The assignments started in these last months are counted.',
        ],
        'medium_frequency_min_assignments' => [
            'label' => 'Medium',
        ],
        'high_frequency_min_assignments' => [
            'label' => 'Frequent',
        ],
    ],
];
