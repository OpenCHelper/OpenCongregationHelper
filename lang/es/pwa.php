<?php

return [
    'install' => [
        'recommended' => 'Recomendado',
        'title' => 'Instalar :app',
        'description' => 'Es la mejor manera de usar :app: recibes sus notificaciones directamente en tu dispositivo.',
        'benefits' => [
            'notifications' => [
                'title' => 'Notificaciones en tu dispositivo',
                'description' => 'Entérate al instante cuando se te asigna un territorio o algo requiere tu atención.',
            ],
            'home_screen' => [
                'title' => 'Siempre a mano',
                'description' => 'Ábrela desde la pantalla de inicio o el escritorio, en su propia ventana, como cualquier otra app.',
            ],
        ],
        'ios_steps_before' => 'Toca',
        'ios_share' => 'Compartir',
        'ios_steps_after' => 'y luego "Añadir a pantalla de inicio" para abrirla como cualquier otra app.',
        'action' => 'Instalar',
        'later' => 'Puedes instalarla más tarde desde el menú de usuario o tu perfil, donde también puedes activar las notificaciones.',
        'menu_label' => 'Instalar app',
        'profile' => [
            'title' => 'Instalar la app',
            'description' => 'Ábrela desde la pantalla de inicio o el escritorio, en su propia ventana, como cualquier otra app.',
            'installed' => 'La app está instalada en este dispositivo.',
        ],
        'dismiss' => 'Ahora no',
    ],
    'notifications' => [
        'title' => 'Activa las notificaciones',
        'description' => 'Recibe las notificaciones de :app directamente en este dispositivo, incluso con la app cerrada.',
        'benefits' => [
            'assignments' => [
                'title' => 'Asignaciones de territorios',
                'description' => 'Entérate al instante cuando se te asigna un territorio.',
            ],
            'reminders' => [
                'title' => 'Recordatorios',
                'description' => 'Recibe un recordatorio de los territorios que tienes desde hace mucho tiempo.',
            ],
            'campaigns' => [
                'title' => 'Campañas',
                'description' => 'Entérate de las nuevas campañas en cuanto empiezan.',
            ],
        ],
        'action' => 'Activar las notificaciones',
        'dismiss' => 'Ahora no',
        'later' => 'El navegador te pedirá que las permitas. Puedes desactivarlas en cualquier momento desde tu perfil.',
    ],
    'offline' => [
        'title' => 'Estás sin conexión',
        'description' => 'Esta página no se puede cargar sin conexión a internet. Comprueba la conexión y vuelve a intentarlo.',
        'retry' => 'Reintentar',
    ],
];
