<?php

return [
    'territory' => [
        'update_existing' => 'Actualiza los registros existentes',
        'image_import' => [
            'label' => '¿Cómo deseas almacenar las imágenes?',
            'store_link' => 'Mantener el enlace',
            'download_image' => 'Guarda una copia',
        ],
        'columns' => [
            'name' => 'Nombre',
            'description' => 'Descripción',
            'image_path' => 'Imagen',
            'image_path_helper' => 'Por ahora, solo se admiten URL absolutas.',
            'geojson' => 'GeoJSON',
            'notes' => 'Notas',
            'is_commercial' => '¿Comercial?',
            'territory_zone' => 'Zona',
        ],
        'notifications' => [
            'completed' => [
                'body' => 'La importación de territorios se ha completado: :count fila importada.|La importación de territorios se ha completado: :count filas importadas.',
                'failed_rows' => ':count fila no se pudo importar.|:count filas no se pudieron importar.',
            ],
        ],
        'errors' => [
            'duplicate' => 'Ya existe un territorio llamado ":name" en la zona ":zone".',
            'update_unauthorized' => 'No tienes permiso para modificar el territorio ":name" de la zona ":zone".',
            'zone_create_unauthorized' => 'La zona ":zone" no existe y no tienes permiso para crearla.',
            'image_unsupported_scheme' => 'Esquema URI ":scheme" no admitido para la imagen ":url".',
            'image_larger_than' => 'la imagen es mayor de :size',
            'image_download_failed' => 'No se pudo descargar la imagen ":url": :reason',
            'image_http_error' => 'No se pudo descargar la imagen ":url": estado HTTP :status.',
            'image_too_large' => 'La imagen ":url" es mayor de :size.',
            'image_invalid_mime_type' => 'Tipo MIME ":mime_type" no válido para la imagen ":url".',
            'image_unresolvable_host' => 'No se pudo resolver el host de la imagen ":url".',
            'image_private_host' => 'La imagen ":url" apunta a una dirección privada o reservada.',
        ],
    ],
    'poster' => [
        'columns' => [
            'code' => 'Código',
            'title' => 'Título',
            'storage' => 'Depósito',
            'quantity' => 'Copias',
            'notes' => 'Notas',
        ],
        'options' => [
            'locale' => 'Idioma de los títulos',
            'locale_helper' => 'El idioma en que están escritos los títulos del archivo: se guardan como su traducción, conservando los demás idiomas.',
        ],
        'examples' => [
            'title' => 'Póster de ejemplo',
            'storage' => 'Depósito A',
            'other_storage' => 'Depósito B',
            'notes' => 'Nota de ejemplo',
        ],
        'notifications' => [
            'completed' => [
                'body' => 'La importación de carteles ha terminado: :count fila importada.|La importación de carteles ha terminado: :count filas importadas.',
                'failed_rows' => 'No se ha podido importar :count fila.|No se han podido importar :count filas.',
            ],
        ],
        'errors' => [
            'unauthorized' => 'No tienes permiso para importar el cartel ":title".',
            'storage_create_unauthorized' => 'El depósito ":storage" no existe y no tienes permiso para crearlo.',
            'quantity_without_storage' => 'Las copias del cartel ":title" no tienen depósito.',
        ],
    ],
    'notifications' => [
        'completed' => [
            'body' => 'La importación ha terminado: :count fila importada.|La importación ha terminado: :count filas importadas.',
            'failed_rows' => 'No se ha podido importar :count fila.|No se han podido importar :count filas.',
        ],
    ],
    'publication' => [
        'columns' => [
            'name' => 'Nombre (:language)',
            'code' => 'Código',
            'category' => 'Categoría',
            'renewal_months' => 'Se renueva cada (meses)',
            'is_active' => 'Disponible',
        ],
        'examples' => [
            'name' => 'Publicación de ejemplo',
            'other_name' => 'Otra publicación',
        ],
        'errors' => [
            'unauthorized' => 'No tienes permiso para importar la publicación ":code".',
            'name_missing' => 'La publicación ":code" no tiene nombre.',
            'category_unknown' => 'La categoría ":category" de la publicación ":code" no existe.',
        ],
    ],
    'storage_location' => [
        'columns' => [
            'name' => 'Nombre',
            'notes' => 'Notas',
        ],
        'examples' => [
            'name' => 'Depósito A',
            'other_name' => 'Depósito B',
            'notes' => 'Nota de ejemplo',
        ],
        'errors' => [
            'unauthorized' => 'No tienes permiso para importar el depósito ":name".',
        ],
    ],
];
