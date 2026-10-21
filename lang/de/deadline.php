<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'Eine Frist rückt näher',
        'body' => '{0} Deine Frist bei :company (:title) ist heute.|{1} Deine Frist bei :company (:title) ist morgen.|[2,*] Deine Frist bei :company (:title) ist in :days Tagen.',
    ],
    'mail' => [
        'subject' => 'Bevorstehende Frist: :company',
        'heading' => 'Bevorstehende Frist bei :company',
        'intro' => 'Deine Frist für :title ist der :date.',
        'open' => 'Meine Bewerbung öffnen',
        'reminder' => 'Du hast dieses Datum selbst festgelegt; wir kontaktieren das Unternehmen nie.',
    ],
];
