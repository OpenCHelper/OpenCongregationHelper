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
        'security' => 'Security',
    ],

    'sections' => [
        'account_requirements' => 'Account requirements',
        'account_requirements_description' => 'What the users must do before they can use the app.',
        'passwords' => 'Passwords',
        'passwords_description' => 'The rules of the passwords chosen by the users.',
        'ai_assistants' => 'AI assistants',
        'ai_assistants_description' => 'Whether the users can connect AI assistants (e.g. ChatGPT, Claude) to the app.',
        'campaign_expiry' => 'Campaign expiry',
        'campaign_expiry_description' => 'As its end date gets closer, an active campaign becomes expiring and then almost expired.',
        'appearance' => 'Appearance',
        'congregation' => 'Congregation',
        'maps' => 'Maps',
        'shared_pages_privacy' => 'Privacy of shared pages',
        'shared_pages_privacy_description' => 'What the public page of a shared assignment shows, and lets do, to anyone who has its link.',
        'stale_assignments' => 'Assignments out for too long',
        'stale_assignments_description' => 'Assignments still in progress after these days are out for too long: once a week, their assignees are reminded to return them and the territory department receives a summary by email and in the app.',
        'assignment_frequency' => 'Assignment frequency',
        'assignment_frequency_description' => 'How frequently each territory is assigned, as coloured on the dashboard map: by the number of its assignments in the last months.',
        'territory_coverage' => 'Territory coverage',
        'territory_coverage_description' => 'The periods the dashboard can show the share of territories worked in.',
    ],

    'fields' => [
        'require_two_factor' => [
            'label' => 'Require two-factor authentication',
            'helper' => 'Users must set up two-factor authentication with an authenticator app before using the app. When off, they can still enable it from their profile.',
            'root_required' => 'Set up two-factor authentication for the root user first, so that it cannot be locked out.',
        ],
        'require_email_verification' => [
            'label' => 'Require email confirmation',
            'helper' => 'Users must confirm their email address, from the link sent to them, before using the app.',
            'root_required' => 'Confirm the email address of the root user first, so that it cannot be locked out.',
        ],
        'password_leak_check' => [
            'label' => 'Refuse compromised passwords',
            'helper' => 'The passwords chosen by the users are checked against the known data leaks of Have I Been Pwned, which receives only the first characters of their hash: a password found there is refused. If the service cannot be reached, the password is accepted.',
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
        ],
        'ai_assistants_enabled' => [
            'label' => 'Allow AI assistants',
            'helper' => 'Each user can connect an AI assistant from their profile: it can read and change what that user can (e.g. territories, assignments), so the data is sent to the provider of the assistant. Turning it off disconnects all of them.',
        ],
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
        'sharing_enabled' => [
            'label' => 'Share assignments',
            'helper' => 'Who can open an assignment can create a public link to it, for those without an account. Disabling it deletes all the existing links: they stop working, even if it is enabled again.',
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
            'confirm_heading' => 'Disable sharing?',
            'confirm_description' => '{1} Saving deletes the :count existing shared link: whoever has it can no longer open the assignment, even if sharing is enabled again.|[2,*] Saving deletes the :count existing shared links: whoever has them can no longer open the assignments, even if sharing is enabled again.',
            'confirm' => 'Disable sharing',
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
        'guest_returns' => [
            'label' => 'Ask to return from shared pages',
            'helper' => 'Anyone who opens the public page of a shared assignment can ask the territory department to close it, without an account.',
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
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
        'stale_summary_recipients' => [
            'label' => 'Recipients of the summary',
            'helper' => 'Who in the territory department receives the weekly summary of the assignments out for too long, by email and in the app.',
            'members' => 'All department members',
            'overseers' => 'Overseers only',
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
        'coverage_periods_months' => [
            'label' => 'Periods',
            'placeholder' => 'Months, e.g. 12',
            'helper' => 'In months: press Enter after each one.',
        ],
        'coverage_default_period_months' => [
            'label' => 'Period shown by default',
        ],
    ],
];
