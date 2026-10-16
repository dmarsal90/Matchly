<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'Aproxima-se um prazo',
        'body' => '{0} O teu prazo na :company (:title) é hoje.|{1} O teu prazo na :company (:title) é amanhã.|[2,*] O teu prazo na :company (:title) é daqui a :days dias.',
    ],
    'mail' => [
        'subject' => 'Prazo a aproximar-se: :company',
        'heading' => 'Prazo a aproximar-se na :company',
        'intro' => 'O teu prazo para :title é a :date.',
        'open' => 'Abrir a minha candidatura',
        'reminder' => 'Esta data foi definida por ti; nunca contactamos a empresa.',
    ],
];
