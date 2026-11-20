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
        'description' => 'The publications, and who receives them on paper.',
    ],
    'roles' => [
        'overseer' => 'Overseer',
        'assistant' => 'Assistant',
    ],
    'resource' => [
        'model_label' => 'Department',
        'plural_model_label' => 'Departments',
        'list_subheading' => 'The members of a department manage its sections. Open a department to edit it.',
        'column_name' => 'Department',
        'column_sections' => 'Sections',
        'column_overseers' => 'Overseers',
        'column_members' => 'Members',
        'field_sections' => 'Sections of the menu',
        'field_sections_helper' => 'The members of the department can manage these sections; only the overseers delete.',
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
        'field_role_helper' => 'The overseers can also delete in its sections, and add and remove its assistants. Only an administrator appoints the overseers.',
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
