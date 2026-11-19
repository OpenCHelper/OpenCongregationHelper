<?php

return [
    'territories' => [
        'label' => 'Territory Department',
        'description' => 'Territories, zones, assignments and campaigns.',
    ],
    'public_witnessing' => [
        'label' => 'Public Witnessing',
        'description' => 'Carts and displays: the inventory of their magnetic posters.',
    ],
    'publications' => [
        'label' => 'Publications',
        'description' => 'Publications and periodicals, e.g. who receives them on paper.',
    ],
    'roles' => [
        'overseer' => 'Overseer',
        'assistant' => 'Assistant',
    ],
    'resource' => [
        'model_label' => 'Department',
        'plural_model_label' => 'Departments',
        'list_subheading' => 'The members of a department can fully manage its sections, without needing a role. Open a department to add or remove its members.',
        'column_name' => 'Department',
        'column_sections' => 'Sections',
        'column_overseers' => 'Overseers',
        'column_members' => 'Members',
        'field_sections' => 'Sections of the menu',
        'field_sections_helper' => 'The members of the department can fully manage these sections.',
        'field_overseers' => 'Overseers',
        'no_overseer' => 'No overseer',
    ],
    'members' => [
        'title' => 'Members',
        'model_label' => 'member',
        'plural_model_label' => 'members',
        'column_name' => 'Name',
        'column_role' => 'Role',
        'column_email' => 'Email',
        'field_users' => 'Brothers',
        'field_role' => 'Role in the department',
        'field_role_helper' => 'The overseers can also add and remove the members of the department.',
        'action_add' => 'Add members',
        'action_add_heading' => 'Add members to :department',
        'action_add_submit' => 'Add',
        'action_change_role' => 'Change role',
        'action_remove' => 'Remove',
        'action_remove_heading' => 'Remove :name from the department',
        'notification_added' => 'Members added',
        'notification_removed' => 'Member removed',
        'empty_heading' => 'No members yet',
        'empty_description' => 'Add the brothers serving in this department: they will be able to manage its sections.',
    ],
];
