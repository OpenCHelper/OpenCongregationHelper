<?php

return [
    'install' => [
        'recommended' => 'Recomendado',
        'title' => 'Instalar :app',
        'description' => 'Es la mejor manera de usar :app: funciona como cualquier otra app de tu dispositivo.',
        'benefits' => [
            'home_screen' => [
                'title' => 'Siempre a mano',
                'description' => 'Ábrela desde la pantalla de inicio o el escritorio, como cualquier otra app.',
            ],
            'own_window' => [
                'title' => 'Más espacio',
                'description' => 'Se abre en su propia ventana, sin la barra de direcciones ni las pestañas del navegador.',
            ],
            'ios_notifications' => [
                'title' => 'Notificaciones en iPhone y iPad',
                'description' => 'Allí, las notificaciones de la app solo funcionan una vez instalada.',
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
