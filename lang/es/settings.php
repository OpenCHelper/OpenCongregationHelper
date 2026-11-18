<?php

return [
    'navigation_label' => 'Ajustes',
    'default' => 'Predeterminado: :value.',
    'on' => 'activado',
    'off' => 'desactivado',
    'days' => 'días',

    'pages' => [
        'general' => 'Generales',
        'territories' => 'Territorios',
        'campaigns' => 'Campañas',
        'notifications' => 'Notificaciones',
    ],

    'sections' => [
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
            'label' => 'Fuera demasiado tiempo después de',
        ],
        'very_stale_after_days' => [
            'label' => 'Mucho tiempo después de',
        ],
        'extremely_stale_after_days' => [
            'label' => 'Muchísimo tiempo después de',
        ],
        'dead_after_days' => [
            'label' => 'Perdidas después de',
        ],
        'stale_check_day' => [
            'label' => 'Día de los recordatorios',
        ],
        'stale_check_time' => [
            'label' => 'Hora de los recordatorios',
            'helper' => 'Hora de Roma.',
        ],
        'expiring_days' => [
            'label' => 'Por vencer',
            'helper' => 'Días antes del final de una campaña desde los que está por vencer.',
        ],
        'almost_expired_days' => [
            'label' => 'Casi vencida',
            'helper' => 'Días antes del final de una campaña desde los que está casi vencida; menos que los de por vencer.',
        ],
        'mail_from_name' => [
            'label' => 'Nombre del remitente de los correos',
            'helper' => 'Se muestra como remitente de los correos enviados por la aplicación.',
        ],
    ],
];
