<?php

return [
    'authorize' => [
        'title' => 'Conectar :name',
        'account' => 'como :name, a :app',
        'can_heading' => 'Este asistente de IA podrá:',
        'can_read' => 'leer lo que puedes ver en la aplicación (p. ej. territorios, asignaciones, personas) y enviarlo a su proveedor;',
        'can_change' => 'hacer en tu nombre lo que puedes hacer en la aplicación (p. ej. asignar y devolver territorios);',
        'can_revoke' => 'mantener el acceso hasta que lo desconectes desde tu perfil.',
        'redirect_notice' => 'Una vez aprobado, volverás a :host. Apruébalo solo si iniciaste la conexión desde allí.',
        'approve' => 'Aprobar',
        'deny' => 'Cancelar',
        'impersonating' => 'Solo el propio usuario puede conectar un asistente de IA, no mientras se le suplanta.',
    ],

    'kinds' => [
        'territory' => 'Territorio',
        'zone' => 'Zona',
        'campaign' => 'Campaña',
        'person' => 'Persona',
        'publication' => 'Publicación',
        'assignment' => 'Asignación',
    ],

    'profile' => [
        'title' => 'Asistentes de IA',
        'description' => 'Conecta un asistente de IA, como ChatGPT o Claude, para preguntarle por los territorios o para que los gestione por ti.',
        'server_url' => 'Dirección del servidor (MCP)',
        'copy' => 'Copiar la dirección',
        'copied' => 'Copiado',
        'how_to' => 'Cómo conectarlo',
        'how_to_claude' => '<strong>Claude</strong>: en Configuración, Conectores, añade un conector personalizado con esta dirección.',
        'how_to_chatgpt' => '<strong>ChatGPT</strong>: en Configuración, Aplicaciones, activa el modo desarrollador en la configuración avanzada y luego crea una aplicación con esta dirección y la autenticación OAuth.',
        'how_to_other' => '<strong>Otros asistentes</strong> (p. ej. Gemini, Claude Code, Cursor, VS Code): añade un servidor MCP remoto (HTTP) con esta dirección.',
        'permissions' => 'Luego el asistente abre esta aplicación para que inicies sesión y lo apruebes: solo puede hacer lo que tú puedes hacer.',
        'connected' => 'Asistentes conectados',
        'connected_at' => 'Conectado el :date',
        'none' => 'Ningún asistente conectado.',
        'disconnect' => 'Desconectar',
        'disconnect_confirm' => '¿Desconectar :name? Habrá que aprobarlo de nuevo para volver a conectarlo.',
        'disconnected' => 'Asistente desconectado',
    ],
];
