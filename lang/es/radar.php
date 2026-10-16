<?php

declare(strict_types=1);

return [
    'title' => 'Radar de empresas',
    'description' => 'Sigue empresas y te avisamos cuando aparezca una oferta nueva suya entre las que recogemos. No visitamos sus webs: solo miramos las ofertas que ya llegan a la app.',
    'list' => 'Empresas que sigues',
    'empty' => 'Aún no sigues ninguna empresa. Pulsa «Seguir empresa» en cualquier oferta.',
    'since' => 'Desde el :date',
    'last' => 'Último aviso: :date',
    'never' => 'Sin avisos todavía',
    'unfollow' => 'Dejar de seguir',
    'search' => 'Ver sus ofertas',
    'follow' => 'Seguir empresa',
    'following' => 'Siguiendo la empresa',
    'manage' => 'Empresas que sigues',
    'too_many' => 'Ya sigues :max empresas. Deja de seguir alguna para añadir otra.',
    'notification' => [
        'title' => 'Ofertas nuevas de una empresa que sigues',
        'body' => '{1} :company tiene :count oferta nueva: :titles|[2,*] :company tiene :count ofertas nuevas: :titles',
    ],
    'mail' => [
        'subject' => '{1} :company tiene una oferta nueva|[2,*] :company tiene :count ofertas nuevas',
        'heading' => '{1} Una oferta nueva en :company|[2,*] :count ofertas nuevas en :company',
        'more' => '{1} Y :count más.|[2,*] Y :count más.',
        'why' => 'Te avisamos porque sigues a :company.',
        'manage' => 'Gestionar las empresas que sigues',
    ],
];
