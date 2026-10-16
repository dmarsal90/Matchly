<?php

declare(strict_types=1);

return [
    'title' => 'Panorama',
    'description' => 'Lo que tu búsqueda de empleo dice de ti. Se calcula con tus afinidades guardadas; nada sale de la app.',
    'skill_gaps' => [
        'title' => 'Qué aprender a continuación',
        'description' => 'Tecnologías que piden tus ofertas seguidas y tus mejores afinidades y que tu perfil no tiene, ordenadas por cuántas ofertas te abrirían.',
        'basis' => 'Basado en :jobs ofertas (:tracked de ellas seguidas por ti).',
        'empty' => 'Aún no hay nada que mostrar. Completa tu perfil de skills y guarda algunas ofertas para ver qué merece la pena aprender.',
        'next' => 'Empieza por :skill',
        'next_hint' => '{0} La piden en :jobs ofertas.|{1} Es la única tecnología obligatoria que te falta en :count oferta, y la piden en :jobs ofertas.|[2,*] Es la única tecnología obligatoria que te falta en :count ofertas, y la piden en :jobs ofertas.',
        'table' => 'Tecnologías que faltan, ordenadas',
        'skill' => 'Tecnología',
        'jobs' => 'Ofertas',
        'required' => 'Obligatoria en',
        'only_gap' => 'Única carencia en',
        'tracked' => 'Seguidas',
    ],
];
