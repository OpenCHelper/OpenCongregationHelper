<?php

return [
    'navigation_label' => 'Ajustes',
    'default' => 'Predeterminado: :value.',
    'on' => 'activado',
    'off' => 'desactivado',

    'units' => [
        'days_before_end' => 'días antes del final',
        'days_after_assignment' => 'días después de la asignación',
    ],

    'stages' => [
        'active_note' => 'hasta que está por vencer',
        'terminated_note' => 'desde la fecha de fin',
        'in_progress' => 'En curso',
        'in_progress_note' => 'desde el día de la asignación',
        'end_date' => 'Fecha de fin',
        'assigned' => 'Asignada',
    ],

    'pages' => [
        'general' => 'Generales',
        'territories' => 'Territorios',
        'campaigns' => 'Campañas',
        'notifications' => 'Notificaciones',
    ],

    'sections' => [
        'campaign_expiry' => 'Vencimiento de las campañas',
        'campaign_expiry_description' => 'A medida que se acerca su fecha de fin, una campaña activa pasa a estar por vencer y luego casi vencida.',
        'application' => 'Aplicación',
        'appearance' => 'Apariencia',
        'maps_and_sharing' => 'Mapas y compartir',
        'stale_assignments' => 'Asignaciones fuera desde hace demasiado tiempo',
        'stale_assignments_description' => 'Las asignaciones todavía en curso después de estos días están fuera desde hace demasiado tiempo: una vez por semana, se recuerda a sus asignados que las devuelvan.',
    ],

    'fields' => [
        'name' => [
            'label' => 'Nombre de la aplicación',
        ],
        'locale' => [
            'label' => 'Idioma predeterminado',
        ],
        'timezone' => [
            'label' => 'Zona horaria',
        ],
        'logo' => [
            'label' => 'Logotipo',
            'helper' => 'Se muestra en la barra superior en lugar del nombre. Como máximo 2 MB.',
        ],
        'primary_color' => [
            'label' => 'Color principal',
            'helper' => 'Color de los botones, enlaces y elementos seleccionados, para los usuarios que no eligieron uno propio en sus preferencias de la interfaz.',
        ],
        'map_default_center' => [
            'label' => 'Centro predeterminado de los mapas',
            'helper' => 'Dónde se abren los mapas mientras ningún territorio tiene un mapa, como "latitud, longitud".',
            'invalid' => 'Escribe las coordenadas como "latitud, longitud" (p. ej. 41.159063, -74.255188).',
        ],
        'shared_pii' => [
            'label' => 'Mostrar los datos personales en las páginas compartidas',
            'helper' => 'La página pública de una asignación compartida muestra también el nombre del asignado y las notas del territorio.',
        ],
        'stale_after_days' => [
            'label' => 'Fuera demasiado tiempo',
        ],
        'dead_after_days' => [
            'label' => 'Perdida',
        ],
        'stale_check_day' => [
            'label' => 'Día de los recordatorios',
        ],
        'stale_check_time' => [
            'label' => 'Hora de los recordatorios',
            'helper' => 'Hora de Roma. Los recordatorios se envían solo si el planificador se está ejecutando a esa hora, como tarea cron o como proceso permanente (p. ej. con Supervisor): consulta la guía de instalación.',
        ],
        'mail_from_name' => [
            'label' => 'Nombre del remitente de los correos',
            'helper' => 'Se muestra como remitente de los correos enviados por la aplicación.',
        ],
    ],
];
