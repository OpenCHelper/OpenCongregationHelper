<?php

return [
    'authorize' => [
        'title' => 'Collega :name',
        'account' => 'come :name, a :app',
        'can_heading' => 'Questo assistente IA potrà:',
        'can_read' => 'leggere ciò che puoi vedere nell\'app (es. territori, assegnazioni, persone) e inviarlo al suo fornitore;',
        'can_change' => 'fare per conto tuo ciò che puoi fare nell\'app (es. assegnare e restituire territori);',
        'can_revoke' => 'mantenere l\'accesso finché non lo scolleghi dal tuo profilo.',
        'redirect_notice' => 'Una volta approvato, tornerai su :host. Approva solo se hai avviato il collegamento da lì.',
        'approve' => 'Approva',
        'deny' => 'Annulla',
        'impersonating' => 'Un assistente IA può essere collegato solo dall\'utente, non impersonandolo.',
    ],

    'profile' => [
        'title' => 'Assistenti IA',
        'description' => 'Collega un assistente IA, come ChatGPT o Claude, per chiedergli dei territori o fargli gestire i territori per te.',
        'server_url' => 'Indirizzo del server (MCP)',
        'copy' => 'Copia l\'indirizzo',
        'copied' => 'Copiato',
        'how_to' => 'Come collegarlo',
        'how_to_claude' => '<strong>Claude</strong>: in Impostazioni, Connettori, aggiungi un connettore personalizzato con questo indirizzo.',
        'how_to_chatgpt' => '<strong>ChatGPT</strong>: in Impostazioni, App, attiva la modalità sviluppatore nelle impostazioni avanzate, poi crea un\'app con questo indirizzo e l\'autenticazione OAuth.',
        'how_to_other' => '<strong>Altri assistenti</strong> (es. Claude Code, Cursor, VS Code): aggiungi un server MCP remoto (HTTP) con questo indirizzo.',
        'permissions' => 'L\'assistente apre poi questa app per farti accedere e approvarlo: può fare solo ciò che puoi fare tu.',
        'connected' => 'Assistenti collegati',
        'connected_at' => 'Collegato il :date',
        'none' => 'Nessun assistente collegato.',
        'disconnect' => 'Scollega',
        'disconnect_confirm' => 'Scollegare :name? Dovrà essere approvato di nuovo per ricollegarsi.',
        'disconnected' => 'Assistente scollegato',
    ],
];
