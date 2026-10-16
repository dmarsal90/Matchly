<?php

declare(strict_types=1);

return [
    'title' => 'O que sei sobre :company',
    'description' => 'Factos que conheces da empresa. A tua carta e o teu pack de entrevista podem usá-los para mostrar interesse real. Só se menciona o que escreveres aqui: nada é inventado.',
    'empty' => 'Ainda não há notas. Adiciona o que te atrai na empresa: o produto, um lançamento recente, os valores.',
    'fact' => 'O que sabes',
    'fact_placeholder' => 'p. ex. Lançaram um SDK de código aberto no ano passado',
    'source' => 'Fonte (ligação, opcional)',
    'source_placeholder' => 'https://…',
    'add' => 'Adicionar nota',
    'approve' => 'Usar',
    'discard' => 'Descartar',
    'suggestions' => 'Sugestões por rever',
    'suggestions_hint' => 'Lê cada uma e fica só com o que for verdadeiro e relevante. Nada disto é usado até aprovares.',
    'approved_title' => 'Prontas a usar',
    'delete' => 'Eliminar nota',
    'delete_confirm' => 'Eliminar esta nota?',
    'origin' => ['manual' => 'Escrita por ti', 'web' => 'Encontrada no site deles'],
    'pending' => 'À espera da tua aprovação',
    'source_link' => 'Fonte',
    'discover' => [
        'title' => 'Procurar no site deles',
        'hint' => 'Lemos as páginas públicas (início, sobre nós, carreiras) respeitando o robots.txt e sugerimos o que dizem de si próprios. Nada é usado até aprovares.',
        'url' => 'Site da empresa',
        'url_placeholder' => 'https://empresa.com',
        'submit' => 'Procurar dados',
        'found' => '{1} :count sugestão encontrada. Revê-a abaixo.|[2,*] :count sugestões encontradas. Revê-as abaixo.',
        'nothing' => 'Lemos o site mas não há nada que mereça ser sugerido.',
        'unreachable' => 'Não foi possível ler esse site (pode bloquear robots ou estar inacessível).',
    ],
];
