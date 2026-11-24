<?php

return [
    'authorize' => [
        'title' => 'Connect :name',
        'account' => 'as :name, to :app',
        'can_heading' => 'This AI assistant will be able to:',
        'can_read' => 'read what you can see in the app (e.g. territories, assignments, people), and send it to its provider;',
        'can_change' => 'do what you can do in the app on your behalf (e.g. assign and return territories);',
        'can_revoke' => 'keep the access until you disconnect it from your profile.',
        'redirect_notice' => 'Once approved, you will go back to :host. Approve only if you started the connection from there.',
        'approve' => 'Approve',
        'deny' => 'Cancel',
        'impersonating' => 'An AI assistant can only be connected by the user, not while impersonating them.',
    ],

    'kinds' => [
        'territory' => 'Territory',
        'zone' => 'Zone',
        'campaign' => 'Campaign',
        'person' => 'Person',
        'publication' => 'Publication',
        'assignment' => 'Assignment',
    ],

    'profile' => [
        'title' => 'AI assistants',
        'description' => 'Connect an AI assistant, such as ChatGPT or Claude, to ask it about the territories or to have it manage them for you.',
        'server_url' => 'Address of the server (MCP)',
        'copy' => 'Copy the address',
        'copied' => 'Copied',
        'how_to' => 'How to connect it',
        'how_to_claude' => '<strong>Claude</strong>: in Settings, Connectors, add a custom connector with this address.',
        'how_to_chatgpt' => '<strong>ChatGPT</strong>: in Settings, Apps, turn on the developer mode in the advanced settings, then create an app with this address and the OAuth authentication.',
        'how_to_other' => '<strong>Other assistants</strong> (e.g. Gemini, Claude Code, Cursor, VS Code): add a remote MCP server (HTTP) with this address.',
        'permissions' => 'The assistant then opens this app to let you log in and approve it: it can do only what you can do.',
        'connected' => 'Connected assistants',
        'connected_at' => 'Connected on :date',
        'none' => 'No assistant connected.',
        'disconnect' => 'Disconnect',
        'disconnect_confirm' => 'Disconnect :name? It will have to be approved again to reconnect.',
        'disconnected' => 'Assistant disconnected',
    ],
];
