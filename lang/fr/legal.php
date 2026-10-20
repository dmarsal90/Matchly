<?php

declare(strict_types=1);

return [
    'back' => 'Retour à l\'accueil',
    'updated' => 'Dernière mise à jour : :date',
    'privacy' => [
        'title' => 'Politique de confidentialité',
        'intro' => ':app ne traite vos données que pour vous aider à trouver du travail à distance, avec les garanties du RGPD.',
        'sections' => [
            'controller' => [
                'title' => 'Responsable du traitement',
                'body' => 'Le responsable du traitement est le propriétaire de :app. Contactez-nous pour toute question de confidentialité grâce aux coordonnées figurant sur le site.',
            ],
            'data' => [
                'title' => 'Ce que nous traitons',
                'body' => 'Les données du compte (nom, e-mail), le profil de carrière que vous saisissez, les CV que vous importez, les données que vous importez depuis GitHub ou LinkedIn avec votre consentement, et un minimum de données techniques pour la sécurité. Nous ne demandons jamais l\'âge, le genre, la nationalité ni de photo.',
            ],
            'purposes' => [
                'title' => 'Pourquoi',
                'body' => 'Pour vous montrer des offres, évaluer votre adéquation, préparer vos candidatures et envoyer des alertes. Chaque traitement facultatif repose sur votre consentement, que vous pouvez retirer dans Paramètres › Confidentialité.',
            ],
            'security' => [
                'title' => 'Sécurité',
                'body' => 'Les fichiers sont stockés chiffrés, le trafic utilise HTTPS et vous seul pouvez accéder à vos données.',
            ],
            'retention' => [
                'title' => 'Conservation',
                'body' => 'Les comptes non vérifiés sont supprimés après :days jours. Le texte extrait des CV des comptes inactifs est supprimé après :months mois. La suppression de votre compte efface tout.',
            ],
            'rights' => [
                'title' => 'Vos droits',
                'body' => 'Vous pouvez accéder à vos données, les corriger, les exporter et les supprimer depuis les Paramètres, et déposer une réclamation auprès de votre autorité de protection des données.',
            ],
            'sources' => [
                'title' => 'Offres de tiers',
                'body' => 'Les offres proviennent de sources publiques et renvoient vers leur origine. Lorsque vous postulez, vous traitez directement avec l\'entreprise.',
            ],
        ],
    ],
    'terms' => [
        'title' => 'Conditions d\'utilisation',
        'intro' => ':app rassemble des offres publiques de travail à distance et vous aide à préparer vos candidatures.',
        'sections' => [
            'service' => [
                'title' => 'Le service',
                'body' => 'Les offres appartiennent à leurs sources d\'origine et sont affichées avec mention de la source et un lien. Nous ne garantissons ni leur exactitude ni leur disponibilité.',
            ],
            'account' => [
                'title' => 'Votre compte',
                'body' => 'Vous êtes responsable de l\'exactitude de votre profil et de la sécurité de vos identifiants.',
            ],
            'ai' => [
                'title' => 'Contenu généré',
                'body' => 'Les lettres de motivation et les CV sur mesure n\'utilisent que les données de votre profil. Relisez-les toujours avant de les envoyer.',
            ],
            'applications' => [
                'title' => 'Candidatures',
                'body' => 'Nous ne postulons jamais à votre place : vous décidez et vous postulez sur le site officiel de chaque offre.',
            ],
        ],
    ],
];
