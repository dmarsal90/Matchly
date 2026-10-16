<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'Se acerca una fecha límite',
        'body' => '{0} Hoy vence tu fecha límite en :company (:title).|{1} Mañana vence tu fecha límite en :company (:title).|[2,*] Tu fecha límite en :company (:title) vence en :days días.',
    ],
    'mail' => [
        'subject' => 'Fecha límite próxima: :company',
        'heading' => 'Fecha límite próxima en :company',
        'intro' => 'Tu fecha límite para :title es el :date.',
        'open' => 'Abrir mi candidatura',
        'reminder' => 'Esta fecha la pusiste tú; no avisamos a la empresa de nada.',
    ],
];
