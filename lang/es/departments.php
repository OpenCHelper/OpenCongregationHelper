<?php

return [
    'territories' => [
        'label' => 'Departamento de Territorios',
        'description' => 'Territorios, zonas, asignaciones y campañas.',
    ],
    'public_witnessing' => [
        'label' => 'Predicación pública',
        'description' => 'Carritos y exhibidores: el inventario de sus carteles magnéticos.',
    ],
    'publications' => [
        'label' => 'Publicaciones',
        'description' => 'Las publicaciones, y quién las recibe en papel.',
    ],
    'roles' => [
        'overseer' => 'Responsable',
        'assistant' => 'Ayudante',
    ],
    'resource' => [
        'model_label' => 'Departamento',
        'plural_model_label' => 'Departamentos',
        'list_subheading' => 'Los miembros de un departamento pueden gestionar sus secciones, sin necesitar un rol (solo los responsables eliminan). Abre un departamento para añadir o quitar sus miembros.',
        'column_name' => 'Departamento',
        'column_sections' => 'Secciones',
        'column_overseers' => 'Responsables',
        'column_members' => 'Miembros',
        'field_sections' => 'Secciones del menú',
        'field_sections_helper' => 'Los miembros del departamento pueden gestionar estas secciones; solo los responsables eliminan.',
        'field_overseers' => 'Responsables',
        'no_overseer' => 'Ningún responsable',
    ],
    'members' => [
        'title' => 'Miembros',
        'model_label' => 'miembro',
        'plural_model_label' => 'miembros',
        'column_name' => 'Nombre',
        'column_role' => 'Rol',
        'column_email' => 'Correo electrónico',
        'field_users' => 'Hermanos',
        'field_role' => 'Rol en el departamento',
        'field_role_helper' => 'Los responsables también pueden eliminar en sus secciones, y añadir y quitar sus ayudantes. Solo un administrador nombra a los responsables.',
        'action_add' => 'Añadir miembros',
        'action_add_heading' => 'Añadir miembros a :department',
        'action_add_submit' => 'Añadir',
        'action_change_role' => 'Cambiar rol',
        'action_remove' => 'Quitar',
        'action_remove_heading' => 'Quitar a :name del departamento',
        'notification_added' => 'Miembros añadidos',
        'notification_removed' => 'Miembro quitado',
        'empty_heading' => 'Todavía no hay miembros',
        'empty_description' => 'Añade los hermanos que sirven en este departamento: podrán gestionar sus secciones.',
    ],
];
