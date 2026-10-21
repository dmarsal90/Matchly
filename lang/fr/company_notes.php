<?php

declare(strict_types=1);

return [
    'title' => 'Ce que je sais sur :company',
    'description' => 'Les faits que vous connaissez sur l\'entreprise. Votre lettre de motivation et votre dossier d\'entretien peuvent les utiliser pour montrer un intérêt réel. Seul ce que vous écrivez ici est mentionné, rien n\'est inventé.',
    'empty' => 'Aucune note pour le moment. Ajoutez ce qui vous attire dans l\'entreprise : son produit, un lancement récent, ses valeurs.',
    'fact' => 'Ce que vous savez',
    'fact_placeholder' => 'p. ex. Ils ont lancé un SDK open source l\'an dernier',
    'source' => 'Source (lien, facultatif)',
    'source_placeholder' => 'https://…',
    'add' => 'Ajouter la note',
    'approve' => 'L\'utiliser',
    'discard' => 'Écarter',
    'suggestions' => 'Suggestions à relire',
    'suggestions_hint' => 'Lisez chacune d\'elles et ne gardez que ce qui est vrai et pertinent. Rien ici n\'est utilisé tant que vous ne l\'avez pas approuvé.',
    'approved_title' => 'Prêt à l\'emploi',
    'delete' => 'Supprimer la note',
    'delete_confirm' => 'Supprimer cette note ?',
    'origin' => ['manual' => 'Rédigée par vous', 'web' => 'Trouvée sur leur site web'],
    'pending' => 'En attente de votre approbation',
    'source_link' => 'Source',
    'discover' => [
        'title' => 'Chercher sur leur site web',
        'hint' => 'Nous lisons leurs pages publiques (accueil, à propos, carrières), en respectant robots.txt, et nous suggérons ce qu\'ils disent d\'eux-mêmes. Rien n\'est utilisé tant que vous ne l\'avez pas approuvé.',
        'url' => 'Site web de l\'entreprise',
        'url_placeholder' => 'https://entreprise.com',
        'submit' => 'Trouver des faits',
        'found' => '{1} :count suggestion trouvée. Relisez-la ci-dessous.|[2,*] :count suggestions trouvées. Relisez-les ci-dessous.',
        'nothing' => 'Nous avons lu le site mais n\'avons rien trouvé qui vaille la peine d\'être suggéré.',
        'unreachable' => 'Nous n\'avons pas pu lire ce site web (il peut bloquer les robots ou être inaccessible).',
    ],
];
