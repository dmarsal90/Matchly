<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'Si avvicina una scadenza',
        'body' => '{0} La tua scadenza presso :company (:title) è oggi.|{1} La tua scadenza presso :company (:title) è domani.|[2,*] La tua scadenza presso :company (:title) è tra :days giorni.',
    ],
    'mail' => [
        'subject' => 'Scadenza in arrivo: :company',
        'heading' => 'Scadenza in arrivo presso :company',
        'intro' => 'La tua scadenza per :title è il :date.',
        'open' => 'Apri la mia candidatura',
        'reminder' => 'Hai impostato tu questa data; non contattiamo mai l\'azienda.',
    ],
];
