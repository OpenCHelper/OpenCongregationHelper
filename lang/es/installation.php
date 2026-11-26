<?php

return [
    'statuses' => [
        'ok' => 'OK',
        'warning' => 'Aviso',
        'error' => 'Error',
    ],

    'checks' => [
        'php_version' => [
            'label' => 'Versión de PHP',
            'failed' => 'PHP :version es demasiado antiguo: la aplicación necesita PHP :minimum o posterior',
        ],
        'php_extensions' => [
            'label' => 'Extensiones de PHP',
            'failed' => 'faltan: :extensions',
        ],
        'app_key' => [
            'label' => 'Clave de la aplicación (APP_KEY)',
            'failed' => 'ejecuta `php artisan key:generate`',
        ],
        'app_url' => [
            'label' => 'URL de la aplicación (APP_URL)',
            'failed' => 'configúrala con la dirección pública: la usan los enlaces de los correos',
        ],
        'https' => [
            'label' => 'Conexión segura (HTTPS)',
            'failed' => 'APP_URL no empieza por https://: los navegadores solo pueden instalar la aplicación y recibir las notificaciones push con HTTPS',
            'invalid' => 'el certificado no es válido, los navegadores mostrarán un aviso: :error',
            'expiring' => 'el certificado caduca el :date: renuévalo',
            'valid' => 'certificado válido hasta el :date',
            'unknown' => 'no se pudo comprobar el certificado desde el servidor (:error): comprueba que los navegadores no muestren avisos',
        ],
        'debug' => [
            'label' => 'Modo de depuración (APP_DEBUG)',
            'failed' => 'desactívalo en producción: los errores mostrarían detalles internos',
        ],
        'timezone' => [
            'label' => 'Zona horaria (APP_TIMEZONE)',
        ],
        'cron_token' => [
            'label' => 'Endpoints de la cola y del programador (ARTISAN_CRON_TOKEN)',
            'enabled' => 'activados',
            'disabled' => 'desactivados (solo necesarios sin una shell)',
        ],
        'mail' => [
            'label' => 'Correo (MAIL_MAILER, MAIL_FROM_ADDRESS)',
            'failed' => 'no se pueden enviar los correos de notificación',
        ],
        'push' => [
            'label' => 'Notificaciones push (VAPID_*)',
            'failed' => 'las notificaciones push están desactivadas: ejecuta `php artisan webpush:vapid`',
        ],
        'initial_root' => [
            'label' => 'Usuario root inicial (INITIAL_ROOT_*)',
            'failed' => 'lo necesita `och:install` en una instalación nueva, y `och:recover-root`',
        ],
        'database' => [
            'label' => 'Conexión a la base de datos',
        ],
        'migrations' => [
            'label' => 'Migraciones',
            'empty' => 'la base de datos está vacía: ejecuta `php artisan och:post-deploy`',
            'failed' => ':count pendientes: ejecuta `php artisan och:post-deploy`',
        ],
        'storage_link' => [
            'label' => 'Enlace del almacenamiento público (public/storage)',
            'failed' => 'falta: los archivos subidos no se pueden mostrar, ejecuta `php artisan storage:link`',
        ],
        'passport_keys' => [
            'label' => 'Claves de los asistentes de IA',
            'failed' => 'faltan: los asistentes de IA no se pueden conectar, ejecuta `php artisan och:install`',
        ],
        'writable_storage' => [
            'label' => 'Almacenamiento con permisos de escritura',
            'failed' => 'storage/framework y storage/logs deben tener permisos de escritura',
        ],
        'env_file' => [
            'label' => 'Archivo .env con permisos de escritura',
            'failed' => 'el asistente de configuración no puede escribir los valores que faltan: configúralos tú en .env',
        ],
        'root_user' => [
            'label' => 'Usuario root (ID 1)',
            'failed' => 'falta: ejecuta `php artisan och:install`',
        ],
        'root_can_login' => [
            'label' => 'El usuario root puede iniciar sesión',
            'failed' => 'inicio de sesión desactivado: ejecuta `php artisan och:recover-root` si nadie más puede gestionar los usuarios',
        ],
        'root_two_factor' => [
            'label' => 'Autenticación en dos pasos del usuario root',
            'failed' => 'recomendada: el usuario root puede hacerlo todo, actívala desde su perfil',
        ],
        'super_admins' => [
            'label' => 'Superadministradores',
            'failed' => 'nadie puede gestionar la aplicación: ejecuta `php artisan och:install`',
        ],
    ],
];
