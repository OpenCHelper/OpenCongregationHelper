<?php

return [
    'title' => 'Instalar la aplicación',
    'subheading' => 'Unos pocos pasos para preparar la aplicación para la congregación. Todo lo que elijas después del usuario root se puede cambiar más tarde desde los ajustes.',
    'complete' => 'Instalar',

    'token' => [
        'missing_heading' => 'Añade el token de configuración',
        'missing_description' => 'La aplicación aún no está instalada. Para que nadie más pueda instalarla antes, añade un token de configuración de tu elección al archivo .env del servidor, p. ej. :example, y vuelve a cargar esta página.',
        'description' => 'Escribe el token de configuración, el valor de SETUP_TOKEN en el archivo .env del servidor.',
        'label' => 'Token de configuración',
        'submit' => 'Continuar',
        'invalid' => 'El token de configuración no es correcto.',
        'throttled' => 'Demasiados intentos: vuelve a intentarlo en :seconds segundos.',
    ],

    'steps' => [
        'requirements' => [
            'label' => 'Servidor',
            'description' => 'Requisitos',
        ],
        'environment' => [
            'label' => 'Configuración',
            'description' => 'El archivo .env',
        ],
        'database' => [
            'label' => 'Base de datos',
            'description' => 'Crear las tablas',
        ],
        'root' => [
            'label' => 'Usuario root',
            'description' => 'Tu cuenta',
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
        'all_ok' => 'El servidor cumple los requisitos.',
        'problems' => '{1} :count comprobación requiere atención.|[2,*] :count comprobaciones requieren atención.',
        'errors' => 'Corrige los errores antes de continuar y vuelve a comprobar.',
        'check_again' => 'Comprobar de nuevo',
        'test_email' => [
            'label' => 'Enviar un correo de prueba',
            'subject' => 'Correo de prueba de :app',
            'body' => 'Este correo se envió desde la instalación de :app: los correos de la aplicación llegan a sus destinatarios.',
            'sent' => 'Correo de prueba enviado a :email.',
            'sent_body' => 'Si no llega en unos minutos, revisa la carpeta de spam y los ajustes de correo (MAIL_*).',
            'failed' => 'No se pudo enviar el correo de prueba.',
        ],
    ],

    'environment' => [
        'already_set' => 'Ya configurado en .env',
        'password_set' => 'Configurada en .env',
        'password_keep' => 'Configurada en .env: déjala vacía para mantenerla',
        'intro' => 'Estos valores faltan en el archivo .env: se añadirán. Los valores ya configurados nunca se cambian.',
        'nothing_missing' => 'Todos los valores que necesita la aplicación ya están en el archivo .env.',
        'not_writable' => 'No se puede escribir el archivo .env: añade tú los valores que faltan y vuelve a cargar esta página.',
        'application' => 'Aplicación',
        'app_url' => 'La dirección pública de la aplicación, usada en los enlaces de los correos.',
        'app_timezone' => 'Se usa para las fechas y la hora de los recordatorios.',
        'app_locale' => 'Idioma de la aplicación para los usuarios que no eligieron uno.',
        'database' => 'Base de datos',
        'db_prefix' => 'Prefijo de los nombres de las tablas, para compartir la base de datos con otras aplicaciones (opcional).',
        'db_sqlite' => 'La ruta completa del archivo de la base de datos: se crea si no existe.',
        'mail' => 'Correo',
        'mailers' => [
            'log' => 'Log: no enviar, solo escribir en el registro',
            'array' => 'Array: no enviar, solo guardar en memoria (pruebas)',
            'failover' => 'Failover: probar por turnos los mailers de la configuración',
            'roundrobin' => 'Round robin: repartir los correos entre los mailers de la configuración',
            'nativemailfx' => 'Función mail() de PHP',
        ],
        'mail_from_address' => 'El remitente de los correos de la aplicación.',
        'database_failed' => 'La aplicación no puede conectarse a la base de datos.',
        'write_failed' => 'No se pudo escribir el archivo .env.',
    ],

    'database' => [
        'intro' => 'Al continuar se crean las tablas de la base de datos, los permisos de los roles y las claves de los asistentes de IA. Puede tardar un minuto.',
        'failed' => 'No se pudo preparar la base de datos (`:command`).',
    ],

    'root' => [
        'intro' => 'El usuario root puede hacerlo todo y nunca se puede eliminar: es tu cuenta. Envía un correo de prueba para comprobar que los correos de la aplicación llegan.',
        'password' => 'Contraseña',
        'password_confirmation' => 'Confirma la contraseña',
        'email_first' => 'Escribe primero una dirección de correo válida.',
        'test_email_hint' => 'Aún no se ha enviado ningún correo de prueba: para exigir la confirmación del correo hace falta uno.',
        'mailer_not_sending' => 'Los correos no se envían (MAIL_MAILER es :mailer): el correo de prueba no llega a ninguna bandeja. Para recibirlo, elige en el paso Configuración un mailer que los envíe.',
    ],

    'administrators' => [
        'intro' => 'Añade a las personas que gestionarán la aplicación contigo: pasan a ser superadministradores y reciben un correo para elegir su contraseña. También puedes añadirlas más tarde.',
        'add' => 'Añadir un administrador',
        'root_email' => 'Esta es la dirección de correo del usuario root.',
    ],

    'features' => [
        'sharing' => 'Compartir asignaciones',
        'sharing_description' => 'Si las asignaciones se pueden compartir mediante un enlace público, con quienes no tienen cuenta.',
        'two_factor_root' => 'La configurarás justo después de iniciar sesión.',
        'email_verification_needs_test' => 'Envía primero un correo de prueba, desde el paso Usuario root: sin correos que funcionen nadie podría confirmar su dirección.',
    ],

    'completed' => [
        'heading' => 'La aplicación está instalada',
        'description' => 'Inicia sesión como usuario root. El token de configuración ya no se usa: puedes quitar SETUP_TOKEN del archivo .env.',
        'log_in' => 'Iniciar sesión',
    ],
];
