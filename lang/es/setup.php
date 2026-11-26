<?php

return [
    'title' => 'Configurar la aplicación',
    'subheading' => 'Unos pocos pasos para preparar la aplicación para la congregación. Todo se puede cambiar más tarde desde los ajustes.',
    'complete' => 'Completar la configuración',

    'steps' => [
        'installation' => [
            'label' => 'Instalación',
            'description' => 'Comprobar el servidor',
        ],
        'congregation' => [
            'label' => 'Congregación',
            'description' => 'Nombre y apariencia',
        ],
        'administrators' => [
            'label' => 'Administradores',
            'description' => 'Quién gestiona la aplicación',
        ],
        'features' => [
            'label' => 'Funciones',
            'description' => 'Qué activar',
        ],
    ],

    'checks' => [
        'all_ok' => 'La instalación parece correcta.',
        'problems' => '{1} :count comprobación requiere atención: la aplicación funciona, pero corrígela cuando puedas.|[2,*] :count comprobaciones requieren atención: la aplicación funciona, pero corrígelas cuando puedas.',
        'check_again' => 'Comprobar de nuevo',
        'storage_link' => 'Crear el enlace del almacenamiento',
        'passport_keys' => 'Crear las claves de los asistentes de IA',
        'fixed' => 'Hecho.',
        'fix_failed' => 'No ha funcionado: ejecuta `:command` en el servidor.',
        'test_email' => [
            'label' => 'Enviar un correo de prueba',
            'subject' => 'Correo de prueba de :app',
            'body' => 'Este correo se envió desde la configuración de :app: los correos de la aplicación llegan a sus destinatarios.',
            'sent' => 'Correo de prueba enviado a :email.',
            'sent_body' => 'Si no llega en unos minutos, revisa la carpeta de spam y los ajustes de correo del servidor (MAIL_*).',
            'failed' => 'No se pudo enviar el correo de prueba.',
        ],
    ],

    'administrators' => [
        'intro' => 'Añade a las personas que gestionarán la aplicación contigo: pasan a ser superadministradores y reciben un correo para elegir su contraseña. Tú (:email) ya lo eres.',
        'add' => 'Añadir un administrador',
    ],

    'features' => [
        'sharing' => 'Compartir asignaciones',
        'sharing_description' => 'Si las asignaciones se pueden compartir mediante un enlace público, con quienes no tienen cuenta.',
    ],

    'skip' => [
        'label' => 'Omitir',
        'heading' => '¿Omitir la configuración?',
        'description' => 'El asistente no se volverá a ofrecer: todo lo que configura se puede cambiar más tarde desde los ajustes y los usuarios.',
        'confirm' => 'Omitir la configuración',
    ],

    'completed' => [
        'title' => 'La aplicación está configurada',
        'body' => '{0} Puedes cambiar estas opciones más tarde desde los ajustes.|{1} Se invitó por correo a :count administrador.|[2,*] Se invitó por correo a :count administradores.',
    ],

    'banner' => [
        'text' => 'La aplicación aún no está configurada: elige su nombre, sus administradores y las funciones que activar.',
        'action' => 'Configurar la aplicación',
    ],
];
