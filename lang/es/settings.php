<?php

return [
    'navigation_label' => 'Ajustes',
    'default' => 'Predeterminado: :value.',
    'on' => 'activado',
    'off' => 'desactivado',

    'units' => [
        'days_before_end' => 'días antes del final',
        'days_after_assignment' => 'días después de la asignación',
        'assignments' => 'asignaciones',
        'months' => 'meses',
    ],

    'stages' => [
        'active_note' => 'hasta que está por vencer',
        'terminated_note' => 'desde la fecha de fin',
        'in_progress' => 'En curso',
        'in_progress_note' => 'desde el día de la asignación',
        'end_date' => 'Fecha de fin',
        'assigned' => 'Asignada',
        'low_frequency' => 'Baja',
        'low_frequency_note' => 'menos asignaciones, también ninguna',
    ],

    'pages' => [
        'general' => 'Generales',
        'territories' => 'Territorios',
        'campaigns' => 'Campañas',
    ],

    'sections' => [
        'campaign_expiry' => 'Vencimiento de las campañas',
        'campaign_expiry_description' => 'A medida que se acerca su fecha de fin, una campaña activa pasa a estar por vencer y luego casi vencida.',
        'appearance' => 'Apariencia',
        'congregation' => 'Congregación',
        'maps_and_sharing' => 'Mapas y compartir',
        'stale_assignments' => 'Asignaciones fuera desde hace demasiado tiempo',
        'stale_assignments_description' => 'Las asignaciones todavía en curso después de estos días están fuera desde hace demasiado tiempo: una vez por semana, se recuerda a sus asignados que las devuelvan.',
        'assignment_frequency' => 'Frecuencia de asignación',
        'assignment_frequency_description' => 'Con qué frecuencia se asigna cada territorio, como se colorea en el mapa del panel: según el número de sus asignaciones en los últimos meses.',
    ],

    'fields' => [
        'kingdom_hall_address' => [
            'label' => 'Dirección del Salón del Reino',
            'placeholder' => 'Calle, número, código postal y ciudad',
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
            'helper' => 'En la zona horaria de la aplicación (`APP_TIMEZONE`). Los recordatorios se envían solo si el planificador se está ejecutando a esa hora, como tarea cron o como proceso permanente (p. ej. con Supervisor): consulta la guía de instalación.',
        ],
        'frequency_period_months' => [
            'label' => 'Meses contados',
            'helper' => 'Se cuentan las asignaciones iniciadas en estos últimos meses.',
        ],
        'medium_frequency_min_assignments' => [
            'label' => 'Media',
        ],
        'high_frequency_min_assignments' => [
            'label' => 'Frecuente',
        ],
    ],
];
