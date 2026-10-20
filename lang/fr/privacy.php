<?php

declare(strict_types=1);

return [
    'title' => 'Confidentialité et données',
    'description' => 'Choisissez ce que nous traitons et exercez vos droits.',
    'consents_title' => 'Consentements',
    'consents_description' => 'Chaque utilisation facultative de vos données nécessite votre autorisation explicite. Vous pouvez la retirer à tout moment.',
    'granted_since' => 'Accordé le :date',
    'consent_saved' => 'Préférence enregistrée.',
    'purposes' => [
        'cv_processing' => [
            'title' => 'Traiter mon CV',
            'description' => 'Extraire le texte, l\'expérience et les compétences des CV que vous importez.',
            'revoke_warning' => 'Le retrait supprime tous vos CV.',
        ],
        'github_import' => [
            'title' => 'Importer depuis GitHub',
            'description' => 'Analyser vos dépôts publics pour détecter les technologies.',
            'revoke_warning' => 'Le retrait supprime les données importées depuis GitHub.',
        ],
        'linkedin_import' => [
            'title' => 'Importer depuis LinkedIn',
            'description' => 'Lire les fichiers de profil de l\'export de vos données LinkedIn.',
            'revoke_warning' => 'Le retrait supprime les données importées depuis LinkedIn.',
        ],
        'portfolio_import' => [
            'title' => 'Importer depuis mon portfolio',
            'description' => 'Lire votre site web public pour détecter les technologies.',
            'revoke_warning' => 'Le retrait supprimera les données importées depuis votre portfolio.',
        ],
        'ai_processing' => [
            'title' => 'Utiliser l\'IA avec mes données',
            'description' => 'Envoyer votre profil à des fournisseurs d\'IA pour générer des lettres de motivation et des CV sur mesure.',
            'revoke_warning' => 'Nous cesserons d\'utiliser l\'IA avec vos données.',
        ],
        'email_alerts' => [
            'title' => 'Alertes par e-mail',
            'description' => 'Recevoir des e-mails avec les offres correspondant à vos recherches enregistrées.',
            'revoke_warning' => 'Vous ne recevrez plus d\'e-mails d\'alerte.',
        ],
    ],
    'export_title' => 'Télécharger mes données',
    'export_description' => 'Un ZIP avec tout ce que nous conservons à votre sujet (JSON lisible) et vos fichiers d\'origine.',
    'export_button' => 'Télécharger le ZIP',
    'delete_title' => 'Supprimer mon compte',
    'delete_description' => 'Supprimez définitivement votre compte et toutes vos données depuis Paramètres › Compte.',
    'delete_link' => 'Aller à la suppression du compte',
    'policy_link' => 'Politique de confidentialité',
    'version' => 'Version de la politique : :version',
];
