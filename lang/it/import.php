<?php

return [
    'territory' => [
        'update_existing' => 'Aggiorna record esistenti',
        'image_import' => [
            'label' => 'Come vuoi che vengano caricate le immagini?',
            'store_link' => 'Lascia link',
            'download_image' => 'Salva una copia',
        ],
        'columns' => [
            'name' => 'Nome',
            'description' => 'Descrizione',
            'image_path' => 'Immagine',
            'image_path_helper' => 'Al momento sono supportati solo URL assoluti.',
            'geojson' => 'GeoJSON',
            'notes' => 'Note',
            'is_commercial' => 'Commerciale?',
            'territory_zone' => 'Zona',
        ],
        'notifications' => [
            'completed' => [
                'body' => 'L\'importazione dei territori è stata completata: :count riga importata.|L\'importazione dei territori è stata completata: :count righe importate.',
                'failed_rows' => ':count riga non è stata importata.|:count righe non sono state importate.',
            ],
        ],
        'errors' => [
            'duplicate' => 'Esiste già un territorio chiamato ":name" nella zona ":zone".',
            'update_unauthorized' => 'Non hai il permesso di modificare il territorio ":name" della zona ":zone".',
            'zone_create_unauthorized' => 'La zona ":zone" non esiste e non hai il permesso di crearla.',
            'image_unsupported_scheme' => 'Schema URI ":scheme" non supportato per l\'immagine ":url".',
            'image_larger_than' => 'l\'immagine è più grande di :size',
            'image_download_failed' => 'Impossibile scaricare l\'immagine ":url": :reason',
            'image_http_error' => 'Impossibile scaricare l\'immagine ":url": stato HTTP :status.',
            'image_too_large' => 'L\'immagine ":url" è più grande di :size.',
            'image_invalid_mime_type' => 'Tipo MIME ":mime_type" non valido per l\'immagine ":url".',
            'image_unresolvable_host' => 'Impossibile risolvere l\'host dell\'immagine ":url".',
            'image_private_host' => 'L\'immagine ":url" punta a un indirizzo privato o riservato.',
        ],
    ],
    'poster' => [
        'columns' => [
            'code' => 'Sigla',
            'title' => 'Titolo',
            'storage' => 'Deposito',
            'quantity' => 'Copie',
            'notes' => 'Note',
        ],
        'options' => [
            'locale' => 'Lingua dei titoli',
            'locale_helper' => 'La lingua in cui sono scritti i titoli nel file: vengono salvati come sua traduzione, conservando le altre lingue.',
        ],
        'examples' => [
            'title' => 'Poster di esempio',
            'storage' => 'Deposito A',
            'other_storage' => 'Deposito B',
            'notes' => 'Nota di esempio',
        ],
        'notifications' => [
            'completed' => [
                'body' => 'L\'importazione dei poster è terminata: :count riga importata.|L\'importazione dei poster è terminata: :count righe importate.',
                'failed_rows' => ':count riga non è stata importata.|:count righe non sono state importate.',
            ],
        ],
        'errors' => [
            'unauthorized' => 'Non hai il permesso di importare il poster ":title".',
            'storage_create_unauthorized' => 'Il deposito ":storage" non esiste e non hai il permesso di crearlo.',
            'quantity_without_storage' => 'Le copie del poster ":title" non hanno un deposito.',
        ],
    ],
    'notifications' => [
        'completed' => [
            'body' => 'L\'importazione è terminata: :count riga importata.|L\'importazione è terminata: :count righe importate.',
            'failed_rows' => 'Non è stato possibile importare :count riga.|Non è stato possibile importare :count righe.',
        ],
    ],
    'storage_location' => [
        'columns' => [
            'name' => 'Nome',
            'notes' => 'Note',
        ],
        'examples' => [
            'name' => 'Deposito A',
            'other_name' => 'Deposito B',
            'notes' => 'Nota di esempio',
        ],
        'errors' => [
            'unauthorized' => 'Non hai il permesso di importare il deposito ":name".',
        ],
    ],
];
