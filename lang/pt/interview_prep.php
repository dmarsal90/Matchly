<?php

declare(strict_types=1);

return [
    'title' => 'Preparar a entrevista',
    'description' => 'Perguntas, esquemas de resposta e lacunas para :title na :company, construídos com o teu próprio CV. São notas para ti: nada é enviado a ninguém.',
    'back' => 'Voltar às minhas ofertas',
    'open' => 'Preparar entrevista',
    'start' => [
        'title' => 'Gera o teu pack',
        'description' => 'Escolhemos as perguntas que provavelmente te farão, esboçamos as tuas respostas com o método STAR a partir do que diz o teu CV e damos-te respostas honestas para as tecnologias que ainda não tens.',
        'submit' => 'Gerar o pack',
        'regenerate' => 'Gerar de novo',
        'use_ai' => 'Melhorar com IA',
        'ai_consent' => 'Aceito enviar o meu perfil e a oferta a um fornecedor de IA para preparar este pack.',
        'no_ai_hint' => 'Sem IA, o pack é construído com modelos e o teu CV.',
    ],
    'generating' => 'A preparar o teu pack…',
    'failed' => 'Não foi possível preparar o pack. Tenta de novo.',
    'source' => ['ai' => 'Escrito com IA e verificado com o teu CV', 'rules' => 'Construído com modelos e o teu CV'],
    'questions' => [
        'title' => 'Perguntas prováveis',
        'categories' => ['technical' => 'Técnica', 'behavioral' => 'Comportamento', 'motivation' => 'Motivação'],
        'star_title' => 'Esquema de resposta (STAR)',
        'situation' => 'Situação',
        'task' => 'Tarefa',
        'action' => 'Ação',
        'result' => 'Resultado',
        'fill_in' => 'Completa isto com um exemplo real teu.',
    ],
    'gaps' => [
        'title' => 'Tecnologias que ainda não tens',
        'description' => 'Diz isso com clareza e mostra como colmatarias a lacuna. Nunca digas que dominas algo que não dominas.',
        'required' => 'Obrigatória',
        'nice' => 'Desejável',
    ],
    'ask' => ['title' => 'Perguntas para lhes fazer'],
    'warnings' => [
        'empty_profile' => 'O teu perfil está quase vazio, por isso os esquemas têm pouco para citar. Completa o teu perfil de skills para um pack melhor.',
        'ai_unavailable' => 'A IA não estava disponível (:reason), por isso o pack foi construído com modelos.',
        'star_removed' => '{1} Foi removido :count esquema porque mencionava uma tecnologia que o teu CV não sustenta.|[2,*] Foram removidos :count esquemas porque mencionavam tecnologias que o teu CV não sustenta.',
    ],
    'template' => [
        'and' => 'e',
        'situation_at' => 'Como :title na :company',
        'technical' => 'Conta-me uma ocasião em que usaste :skill para resolver um problema real.',
        'behavioral' => [
            'conflict' => 'Conta-me um desacordo com um colega e como o geriste.',
            'failure' => 'Conta-me algo que correu mal num projeto e o que aprendeste.',
            'deadline' => 'Conta-me uma vez em que tiveste de entregar com um prazo muito apertado.',
        ],
        'motivation' => [
            'company' => 'Porque queres trabalhar na :company?',
            'role' => 'Porque te interessa a vaga de :title?',
        ],
        'gap' => [
            'close' => 'Ainda não trabalhei com :skill de forma profissional e prefiro dizê-lo abertamente. A minha experiência mais próxima é com :closest. Atualizar-me-ia com a documentação e um pequeno projeto prático nas primeiras semanas.',
            'none' => 'Ainda não trabalhei com :skill de forma profissional e prefiro dizê-lo abertamente. Atualizar-me-ia com a documentação e um pequeno projeto prático nas primeiras semanas.',
        ],
        'ask' => [
            'fact' => 'Vi que :fact. Como é que isso influencia o dia a dia da equipa?',
            'team' => 'Como está organizada a equipa e com quem trabalharia no dia a dia?',
            'success' => 'O que significaria ter sucesso nos primeiros seis meses como :title?',
            'process' => 'Quais são os próximos passos do processo na :company?',
        ],
    ],
];
