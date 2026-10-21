<?php

declare(strict_types=1);

return [
    'title' => 'Unternehmensradar',
    'description' => 'Folge Unternehmen und wir sagen dir Bescheid, wenn eine neue Stelle von ihnen unter den gesammelten auftaucht. Wir besuchen ihre Websites nicht: Wir sehen uns nur die Stellen an, die ohnehin in der App ankommen.',
    'list' => 'Unternehmen, denen du folgst',
    'empty' => 'Du folgst noch keinem Unternehmen. Klicke bei einer beliebigen Stelle auf „Unternehmen folgen“.',
    'since' => 'Seit :date',
    'last' => 'Letzte Mitteilung: :date',
    'never' => 'Noch keine Mitteilungen',
    'unfollow' => 'Nicht mehr folgen',
    'search' => 'Stellen ansehen',
    'follow' => 'Unternehmen folgen',
    'following' => 'Du folgst diesem Unternehmen',
    'manage' => 'Unternehmen, denen du folgst',
    'too_many' => 'Du folgst bereits :max Unternehmen. Entfolge einem, um ein weiteres hinzuzufügen.',
    'notification' => [
        'title' => 'Neue Stellen eines Unternehmens, dem du folgst',
        'body' => '{1} :company hat :count neue Stelle: :titles|[2,*] :company hat :count neue Stellen: :titles',
    ],
    'mail' => [
        'subject' => '{1} :company hat eine neue Stelle|[2,*] :company hat :count neue Stellen',
        'heading' => '{1} Eine neue Stelle bei :company|[2,*] :count neue Stellen bei :company',
        'more' => '{1} Und :count weitere.|[2,*] Und :count weitere.',
        'why' => 'Du erhältst dies, weil du :company folgst.',
        'manage' => 'Unternehmen verwalten, denen du folgst',
    ],
];
