<?php

return [
    'navigation_label' => 'Ajustes',
    'default' => 'Predeterminado: :value.',
    'on' => 'activado',
    'off' => 'desactivado',

    'pages' => [
        'general' => 'Generales',
        'territories' => 'Territorios',
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
        'map_default_center' => [
            'label' => 'Centro predeterminado de los mapas',
            'helper' => 'Dónde se abren los mapas mientras ningún territorio tiene un mapa, como "latitud, longitud".',
            'invalid' => 'Escribe las coordenadas como "latitud, longitud" (p. ej. 41.159063, -74.255188).',
        ],
        'shared_pii' => [
            'label' => 'Mostrar los datos personales en las páginas compartidas',
            'helper' => 'La página pública de una asignación compartida muestra también el nombre del asignado y las notas del territorio.',
        ],
    ],
];
