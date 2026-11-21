<?php

return [
    'navigation_label' => 'Ajustes',
    'default' => 'Predeterminado: :value.',

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
        'maps' => 'Mapas',
        'shared_pages_privacy' => 'Privacidad de las páginas compartidas',
        'shared_pages_privacy_description' => 'Lo que muestra y permite hacer la página pública de una asignación compartida a cualquiera que tenga su enlace.',
        'stale_assignments' => 'Asignaciones fuera desde hace demasiado tiempo',
        'stale_assignments_description' => 'Las asignaciones todavía en curso después de estos días están fuera desde hace demasiado tiempo: una vez por semana, se recuerda a sus asignados que las devuelvan y el departamento de territorios recibe un resumen por correo y en la aplicación.',
        'assignment_frequency' => 'Frecuencia de asignación',
        'assignment_frequency_description' => 'Con qué frecuencia se asigna cada territorio, como se colorea en el mapa del panel: según el número de sus asignaciones en los últimos meses.',
        'territory_coverage' => 'Cobertura del territorio',
        'territory_coverage_description' => 'Los periodos en los que el panel puede mostrar la parte de territorios trabajados.',
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
        'sharing_enabled' => [
            'label' => 'Compartir asignaciones',
            'helper' => 'Quien puede abrir una asignación puede crear un enlace público a ella, para quienes no tienen una cuenta. Si se desactiva, no se puede crear ningún enlace y los existentes dejan de funcionar hasta que se vuelva a activar.',
            'enabled' => 'Activado',
            'disabled' => 'Desactivado',
        ],
        'shared_pii' => [
            'hide' => 'Ocultar los datos personales',
            'hide_description' => 'Solo el territorio, su mapa y las fechas: el asignado y las notas se quedan en la aplicación.',
            'show' => 'Mostrar los datos personales',
            'show_description' => 'También el nombre del asignado y las notas del territorio, a cualquiera que tenga el enlace. Los enlaces nunca caducan y se pueden reenviar.',
            'recommended' => 'Recomendado',
            'confirm_heading' => '¿Mostrar los datos personales en las páginas compartidas?',
            'consequences' => [
                'Cualquiera que tenga el enlace de una asignación compartida verá el nombre del asignado y las notas del territorio, que pueden describir a los moradores.',
                'Los enlaces compartidos nunca caducan y se pueden reenviar, por ejemplo en un chat de grupo.',
                'Hazlo solo si la congregación está de acuerdo y las notas no contienen datos personales.',
            ],
            'understood' => 'Entiendo que cualquiera con un enlace compartido verá estos datos, y la congregación está de acuerdo.',
            'understood_required' => 'Confirma que entiendes lo que supone mostrar los datos personales.',
            'confirm' => 'Mostrar los datos personales',
        ],
        'guest_returns' => [
            'label' => 'Solicitar la devolución desde las páginas compartidas',
            'helper' => 'Cualquiera que abra la página pública de una asignación compartida puede pedir al departamento de territorios que la cierre, sin una cuenta.',
            'enabled' => 'Activado',
            'disabled' => 'Desactivado',
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
        'stale_summary_recipients' => [
            'label' => 'Destinatarios del resumen',
            'helper' => 'Quién en el departamento de territorios recibe el resumen semanal de las asignaciones fuera desde hace demasiado tiempo, por correo y en la aplicación.',
            'members' => 'Todos los miembros del departamento',
            'overseers' => 'Solo los responsables',
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
        'coverage_periods_months' => [
            'label' => 'Periodos',
            'placeholder' => 'Meses, p. ej. 12',
            'helper' => 'En meses: pulsa Intro después de cada uno.',
        ],
        'coverage_default_period_months' => [
            'label' => 'Periodo mostrado por defecto',
        ],
    ],
];
