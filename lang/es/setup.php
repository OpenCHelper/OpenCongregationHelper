<?php

return [
    'title' => 'Instalar la aplicación',
    'subheading' => 'Unos pocos pasos para preparar la aplicación para la congregación.',
    'changeable_later' => 'Todo lo que elijas después del usuario root se puede cambiar más tarde desde los ajustes.',
    'complete' => 'Instalar',

    'token' => [
        'missing_heading' => 'Añade el token de configuración',
        'missing_description' => 'La aplicación aún no está instalada. Para que nadie más pueda instalarla antes, añade un token de configuración de tu elección al archivo .env del servidor, p. ej. :example, y vuelve a cargar esta página.',
        'description' => 'Escribe el valor de SETUP_TOKEN, del archivo .env del servidor.',
        'label' => 'Token de configuración',
        'submit' => 'Continuar',
        'invalid' => 'El token de configuración no es correcto.',
        'throttled' => 'Demasiados intentos: vuelve a intentarlo en :seconds segundos.',
    ],

    'steps' => [
        'counter' => 'Paso :step de :total',
        'requirements' => [
            'label' => 'Servidor',
            'description' => 'Requisitos',
        ],
        'environment' => [
            'label' => 'Configuración',
            'description' => 'El archivo .env',
        ],
        'tasks' => [
            'label' => 'Tareas en segundo plano',
            'description' => 'Programador y cola',
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
        'review' => [
            'label' => 'Resumen',
            'description' => 'Revisa e instala',
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
        'default_option' => 'Predeterminado: :value',
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
        'keys' => 'Claves',
        'push_keys' => 'Notificaciones push (VAPID_*)',
        'passport_keys' => 'Asistentes de IA',
        'push_not_writable' => 'No se puede escribir el archivo .env: créalas con php artisan webpush:vapid.',
        'key_statuses' => [
            'set' => 'Ya configuradas',
            'created' => 'Se crean al continuar',
            'unavailable' => 'No se pueden crear',
        ],
        'keys_failed' => 'No se pudieron crear las claves (`:command`).',
        'mail_from_address' => 'El remitente de los correos de la aplicación.',
        'database_failed' => 'La aplicación no puede conectarse a la base de datos.',
        'write_failed' => 'No se pudo escribir el archivo .env.',
    ],

    'tasks' => [
        'intro' => 'Parte del trabajo se hace en segundo plano: las tareas programadas (p. ej. los recordatorios semanales) y, con una cola, el envío de los correos. Configúralo ahora o después de la instalación: la aplicación funciona igualmente, pero estas tareas no se ejecutan.',
        'queue_helper' => 'Donde los correos y el resto del trabajo en segundo plano esperan a ser procesados.',
        'with_shell' => 'Con una shell',
        'without_shell' => 'Sin una shell',
        'cron_intro' => 'Añade esta línea al crontab del servidor (crontab -e), para ejecutar el programador cada minuto:',
        'worker_intro' => 'Y esta para procesar la cola (o ejecuta php artisan queue:work como proceso continuo, p. ej. con Supervisor):',
        'sync_note' => 'Con esta cola nada queda en espera: el trabajo se hace en el momento y no hace falta un worker.',
        'service_intro' => 'En un hosting sin shell, haz que un servicio cron externo llame a estas direcciones cada minuto, con el método POST:',
        'token_intro' => 'Cada llamada debe enviar esta cabecera, que demuestra que viene de tu servicio:',
        'token_disabled' => 'Estas direcciones están desactivadas hasta que tengan un secreto (ARTISAN_CRON_TOKEN): actívalas solo si las usas.',
        'enable_endpoints' => 'Activar las direcciones',
        'copy' => 'Copiar',
        'queues' => [
            'sync' => 'Sync: en el momento, durante la petición (sin worker)',
            'database' => 'Database: en la base de datos, procesada por un worker (recomendada)',
        ],
    ],
    'database' => [
        'intro' => 'Al continuar se crean las tablas de la base de datos y los permisos de los roles. Puede tardar un minuto.',
        'running' => 'Creando las tablas… Puede tardar un minuto: no cierres la página.',
        'failed' => 'No se pudo preparar la base de datos (`:command`).',
    ],

    'root' => [
        'intro' => 'El usuario root puede hacerlo todo y nunca se puede eliminar: es tu cuenta. Envía un correo de prueba para comprobar que los correos de la aplicación llegan.',
        'password' => 'Contraseña',
        'password_hint' => 'Al menos :min caracteres.',
        'password_hint_uncompromised' => 'No debe estar entre las contraseñas filtradas en internet.',
        'generate_password' => 'Generar una contraseña',
        'password_generated' => 'Contraseña generada',
        'password_generated_body' => 'Guárdala ahora, p. ej. en un gestor de contraseñas: :password',
        'password_confirmation' => 'Confirma la contraseña',
        'email_first' => 'Escribe primero una dirección de correo válida.',
        'test_email_hint' => 'Aún no se ha enviado ningún correo de prueba: para exigir la confirmación del correo hace falta uno.',
        'mailer_not_sending' => 'Los correos no se envían (MAIL_MAILER es :mailer): el correo de prueba no llega a ninguna bandeja. Para recibirlo, elige en el paso Configuración un mailer que los envíe.',
        'go_to_configuration' => 'Ir al paso Configuración',
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

    'review' => [
        'intro' => 'Revisa tus elecciones y luego instala. Vuelve a cualquier paso para cambiarlas.',
        'edit' => 'Editar',
        'test_email' => 'Correo de prueba',
        'test_email_sent' => 'Enviado a :email',
        'test_email_not_sent' => 'No enviado',
        'none' => 'Ninguno',
        'enabled' => 'Sí',
        'disabled' => 'No',
    ],

    'completed' => [
        'heading' => 'La aplicación está instalada',
        'description' => 'Inicia sesión como usuario root para empezar a usar la aplicación.',
        'items' => [
            'database' => 'Tablas y permisos creados',
            'root' => 'Usuario root creado: :email',
            'settings' => 'Ajustes guardados',
            'administrators' => '{0} Ningún administrador añadido: puedes añadirlos más tarde desde los usuarios|{1} :count administrador invitado por correo|[2,*] :count administradores invitados por correo',
            'invitations_failed' => '{1} :count invitación no se pudo enviar: envíala de nuevo desde los usuarios|[2,*] :count invitaciones no se pudieron enviar: envíalas de nuevo desde los usuarios',
            'two_factor' => 'Configura la autenticación en dos pasos justo después de iniciar sesión',
            'token_removed' => 'SETUP_TOKEN quitado del archivo .env',
            'remove_token' => 'Quita SETUP_TOKEN del archivo .env: ya no se usa',
        ],
        'log_in' => 'Iniciar sesión',
    ],
];
