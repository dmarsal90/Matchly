<?php

declare(strict_types=1);

return [
    'title' => 'Radar des entreprises',
    'description' => 'Suivez des entreprises et nous vous prévenons quand une nouvelle offre de leur part apparaît parmi celles que nous collectons. Nous ne visitons pas leurs sites web : nous regardons seulement les offres qui arrivent déjà dans l\'application.',
    'list' => 'Entreprises que vous suivez',
    'empty' => 'Vous ne suivez encore aucune entreprise. Cliquez sur « Suivre l\'entreprise » sur n\'importe quelle offre.',
    'since' => 'Depuis le :date',
    'last' => 'Dernier avis : :date',
    'never' => 'Aucun avis pour le moment',
    'unfollow' => 'Ne plus suivre',
    'search' => 'Voir ses offres',
    'follow' => 'Suivre l\'entreprise',
    'following' => 'Vous suivez cette entreprise',
    'manage' => 'Entreprises que vous suivez',
    'too_many' => 'Vous suivez déjà :max entreprises. Arrêtez d\'en suivre une pour en ajouter une autre.',
    'notification' => [
        'title' => 'Nouvelles offres d\'une entreprise que vous suivez',
        'body' => '{1} :company a :count nouvelle offre : :titles|[2,*] :company a :count nouvelles offres : :titles',
    ],
    'mail' => [
        'subject' => '{1} :company a une nouvelle offre|[2,*] :company a :count nouvelles offres',
        'heading' => '{1} Une nouvelle offre chez :company|[2,*] :count nouvelles offres chez :company',
        'more' => '{1} Et :count autre.|[2,*] Et :count autres.',
        'why' => 'Vous recevez ceci parce que vous suivez :company.',
        'manage' => 'Gérer les entreprises que vous suivez',
    ],
];
