<?php

declare(strict_types=1);

return [
    'title' => 'Radar de empresas',
    'description' => 'Segue empresas e avisamos-te quando surgir uma oferta nova delas entre as que recolhemos. Não visitamos os sites delas: só vemos as ofertas que já chegam à app.',
    'list' => 'Empresas que segues',
    'empty' => 'Ainda não segues nenhuma empresa. Clica em «Seguir empresa» em qualquer oferta.',
    'since' => 'Desde :date',
    'last' => 'Último aviso: :date',
    'never' => 'Sem avisos ainda',
    'unfollow' => 'Deixar de seguir',
    'search' => 'Ver as ofertas',
    'follow' => 'Seguir empresa',
    'following' => 'A seguir a empresa',
    'manage' => 'Empresas que segues',
    'too_many' => 'Já segues :max empresas. Deixa de seguir uma para adicionar outra.',
    'notification' => [
        'title' => 'Ofertas novas de uma empresa que segues',
        'body' => '{1} A :company tem :count oferta nova: :titles|[2,*] A :company tem :count ofertas novas: :titles',
    ],
    'mail' => [
        'subject' => '{1} A :company tem uma oferta nova|[2,*] A :company tem :count ofertas novas',
        'heading' => '{1} Uma oferta nova na :company|[2,*] :count ofertas novas na :company',
        'more' => '{1} E mais :count.|[2,*] E mais :count.',
        'why' => 'Recebes isto porque segues a :company.',
        'manage' => 'Gerir as empresas que segues',
    ],
];
