<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'Une échéance approche',
        'body' => '{0} Votre échéance chez :company (:title) est aujourd\'hui.|{1} Votre échéance chez :company (:title) est demain.|[2,*] Votre échéance chez :company (:title) est dans :days jours.',
    ],
    'mail' => [
        'subject' => 'Échéance à venir : :company',
        'heading' => 'Échéance à venir chez :company',
        'intro' => 'Votre échéance pour :title est le :date.',
        'open' => 'Ouvrir ma candidature',
        'reminder' => 'Vous avez fixé cette date vous-même ; nous ne contactons jamais l\'entreprise.',
    ],
];
