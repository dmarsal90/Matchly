<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'Hora de fazer seguimento?',
        'body' => 'A :company ainda não respondeu à tua candidatura a :title há :days dias.',
        'open' => 'Abrir as minhas ofertas',
    ],
    'mail' => [
        'subject' => 'Fazer seguimento com a :company?',
        'heading' => 'Ainda sem resposta da :company',
        'intro' => 'Candidataste-te a :title em :date e não tiveste notícias desde então. Um seguimento breve costuma ajudar. Aqui fica um rascunho que podes adaptar e enviar tu mesmo:',
        'open' => 'Abrir a minha candidatura',
        'reminder' => 'É apenas um rascunho: nunca contactamos empresas por ti.',
    ],
    'draft' => [
        'greeting' => ['formal' => 'Exmos. Senhores,', 'friendly' => 'Olá,', 'enthusiastic' => 'Olá!'],
        'body' => 'Candidatei-me em :date à vaga de :title na :company e gostaria de saber como está o processo. Continuo muito interessado na vaga.',
        'close' => [
            'formal' => 'Agradeço desde já a vossa atenção e fico à disposição para qualquer informação adicional.',
            'friendly' => 'Fico disponível para conversar ou enviar o que precisarem. Obrigado!',
            'enthusiastic' => 'Adorava saber em que ponto estamos! Muito obrigado!',
        ],
        'sign_off' => ['formal' => 'Com os melhores cumprimentos,', 'friendly' => 'Cumprimentos,', 'enthusiastic' => 'Um abraço!'],
    ],
];
