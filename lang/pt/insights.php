<?php

declare(strict_types=1);

return [
    'title' => 'Panorama',
    'description' => 'O que a tua procura de emprego diz sobre ti. Calculado com as tuas afinidades guardadas; nada sai da app.',
    'skill_gaps' => [
        'title' => 'O que aprender a seguir',
        'description' => 'Tecnologias que as tuas ofertas seguidas e melhores afinidades pedem e que o teu perfil não tem, ordenadas por quantas ofertas te abririam.',
        'basis' => 'Com base em :jobs ofertas (:tracked delas seguidas por ti).',
        'empty' => 'Ainda não há nada para mostrar. Completa o teu perfil de skills e guarda algumas ofertas para ver o que vale a pena aprender.',
        'next' => 'Começa por :skill',
        'next_hint' => '{0} É pedida em :jobs ofertas.|{1} É a única tecnologia obrigatória que te falta em :count oferta, e é pedida em :jobs ofertas.|[2,*] É a única tecnologia obrigatória que te falta em :count ofertas, e é pedida em :jobs ofertas.',
        'table' => 'Tecnologias em falta, ordenadas',
        'skill' => 'Tecnologia',
        'jobs' => 'Ofertas',
        'required' => 'Obrigatória em',
        'only_gap' => 'Única lacuna em',
        'tracked' => 'Seguidas',
    ],
];
