<?php

declare(strict_types=1);

return [
    'title' => 'Lo que sé de :company',
    'description' => 'Datos que conoces de la empresa. Tu carta y tu pack de entrevista pueden usarlos para mostrar interés real. Solo se menciona lo que escribas aquí: nada se inventa.',
    'empty' => 'Aún no hay notas. Añade lo que te atrae de la empresa: su producto, un lanzamiento reciente, sus valores.',
    'fact' => 'Lo que sabes',
    'fact_placeholder' => 'p. ej. Lanzaron un SDK de código abierto el año pasado',
    'source' => 'Fuente (enlace, opcional)',
    'source_placeholder' => 'https://…',
    'add' => 'Añadir nota',
    'approve' => 'Usarla',
    'discard' => 'Descartar',
    'suggestions' => 'Sugerencias por revisar',
    'suggestions_hint' => 'Léelas y quédate solo con lo que sea cierto y relevante. Nada de esto se usa hasta que lo apruebes.',
    'approved_title' => 'Listas para usar',
    'delete' => 'Eliminar nota',
    'delete_confirm' => '¿Eliminar esta nota?',
    'origin' => ['manual' => 'Escrita por ti', 'web' => 'Encontrada en su web'],
    'pending' => 'Pendiente de tu aprobación',
    'source_link' => 'Fuente',
    'discover' => [
        'title' => 'Buscar en su web',
        'hint' => 'Leemos sus páginas públicas (inicio, quiénes somos, empleo) respetando robots.txt y te sugerimos lo que dicen de sí mismos. No se usa nada hasta que lo apruebes.',
        'url' => 'Web de la empresa',
        'url_placeholder' => 'https://empresa.com',
        'submit' => 'Buscar datos',
        'found' => '{1} :count sugerencia encontrada. Revísala abajo.|[2,*] :count sugerencias encontradas. Revísalas abajo.',
        'nothing' => 'Hemos leído la web pero no hay nada que merezca sugerirse.',
        'unreachable' => 'No hemos podido leer esa web (puede bloquear robots o no estar accesible).',
    ],
];
