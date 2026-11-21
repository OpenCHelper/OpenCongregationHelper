<?php

return [
    'action' => [
        'label' => 'S-13',
        'modal_heading' => 'Registro de asignación de territorios (S-13)',
        'modal_description' => 'Descarga el S-13 de un año de servicio, rellenado con las asignaciones de los territorios en el formulario oficial.',
        'submit' => 'Descargar',
    ],
    'fields' => [
        'service_year' => [
            'label' => 'Año de servicio',
            'helper' => 'Un año de servicio va del 1 de septiembre al 31 de agosto.',
            'option' => ':year (septiembre :from – agosto :to)',
        ],
        'template' => [
            'label' => 'Formulario S-13 oficial',
            'replace_label' => 'Reemplazar el formulario S-13 oficial',
            'helper' => 'El PDF del S-13 (Registro de asignación de territorios), descargado de JW Hub, en el idioma de la congregación.',
            'invalid' => 'Este archivo no es un PDF que se pueda usar: sube el S-13 descargado de JW Hub.',
        ],
    ],
    'template' => [
        'missing_heading' => 'Todavía no se ha subido el formulario S-13 oficial',
        'missing_admin' => 'Sube el S-13 descargado de JW Hub: se guardará para las próximas descargas.',
        'missing_user' => 'Pide a un superadministrador que suba el S-13 descargado de JW Hub.',
    ],
    'structure' => [
        'stored_heading' => 'El formulario S-13 subido es distinto del que la app espera',
        'uploaded_heading' => 'Este formulario S-13 es distinto del que la app espera',
        'description' => 'Probablemente es una nueva edición del formulario: el registro podría escribirse en lugares equivocados. Infórmalo, adjuntando el formulario, para que se actualice la app. Diferencias:',
        'report' => 'Informar en GitHub',
        'accept' => 'Usar este formulario de todos modos',
        'accept_required' => 'Confirma que se debe usar este formulario aunque sea distinto del esperado.',
        'pages' => 'Páginas: :actual en lugar de :expected',
        'size' => 'Tamaño de la página: :actual en lugar de :expected',
        'missing_fields' => 'Campos que faltan: :fields',
        'added_fields' => 'Campos nuevos: :fields',
        'grid' => 'Las líneas de la tabla están en lugares distintos',
        'and_more' => 'y :count más',
    ],
];
