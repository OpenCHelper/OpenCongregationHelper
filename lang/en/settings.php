<?php

return [
    'navigation_label' => 'Settings',
    'default' => 'Default: :value.',
    'on' => 'on',
    'off' => 'off',

    'units' => [
        'days_before_end' => 'days before the end',
        'days_after_assignment' => 'days after the assignment',
    ],

    'stages' => [
        'active_note' => 'until it is expiring',
        'terminated_note' => 'from the end date',
        'in_progress' => 'In progress',
        'in_progress_note' => 'from the day of the assignment',
        'end_date' => 'End date',
        'assigned' => 'Assigned',
    ],

    'pages' => [
        'general' => 'General',
        'territories' => 'Territories',
        'campaigns' => 'Campaigns',
        'notifications' => 'Notifications',
    ],

    'sections' => [
        'campaign_expiry' => 'Campaign expiry',
        'campaign_expiry_description' => 'As its end date gets closer, an active campaign becomes expiring and then almost expired.',
        'application' => 'Application',
        'appearance' => 'Appearance',
        'maps_and_sharing' => 'Maps and sharing',
        'stale_assignments' => 'Assignments out for too long',
        'stale_assignments_description' => 'Assignments still in progress after these days are out for too long: once a week, their assignees are reminded to return them.',
    ],

    'fields' => [
        'name' => [
            'label' => 'Name of the application',
        ],
        'locale' => [
            'label' => 'Default language',
        ],
        'timezone' => [
            'label' => 'Timezone',
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
            'label' => 'Show personal data on shared pages',
            'helper' => 'The public page of a shared assignment also shows the name of the assignee and the notes of the territory.',
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
            'helper' => 'In the timezone of the application (General page). The reminders are sent only if the scheduler is running at that time, as a cron job or as a long-running process (e.g. with Supervisor): see the setup guide.',
        ],
        'mail_from_name' => [
            'label' => 'Sender name of the emails',
            'helper' => 'Shown as the sender of the emails sent by the application.',
        ],
    ],
];
