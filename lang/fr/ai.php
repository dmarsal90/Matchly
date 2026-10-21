<?php

declare(strict_types=1);

return [
    'title' => 'Intelligence artificielle',
    'description' => 'L\'IA structure votre CV, résume votre GitHub et extrait les exigences des offres. Elle n\'invente jamais d\'expérience : vous relisez tout avant l\'enregistrement.',
    'status' => [
        'available' => 'L\'IA est disponible.',
        'unavailable' => 'Aucun fournisseur d\'IA n\'est disponible pour le moment. Vous pouvez utiliser votre propre clé Anthropic.',
    ],
    'consent' => [
        'given' => 'Vous avez consenti au traitement de vos données par l\'IA.',
        'missing' => 'Vous n\'avez pas encore consenti au traitement de vos données par l\'IA. Votre accord vous sera demandé lorsque vous utiliserez une fonction d\'IA.',
        'manage' => 'Gérer dans Confidentialité',
    ],
    'usage' => [
        'title' => 'Utilisation ce mois-ci',
        'calls' => '{0} Aucun appel|{1} :count appel|[2,*] :count appels',
        'budget' => 'Budget inclus : :used sur :limit USD',
        'own_key_cost' => 'Coût sur votre clé : :cost USD (facturé directement par Anthropic).',
        'tasks' => [
            'extract_job_requirements' => 'Exigences de l\'offre',
            'structure_cv' => 'Structuration du CV',
            'summarize_github' => 'Résumé GitHub',
            'tailor_cv' => 'Adaptation du CV',
            'cover_letter' => 'Lettre de motivation',
        ],
    ],
    'key' => [
        'title' => 'Votre clé Anthropic (facultatif)',
        'description' => 'Avec votre propre clé, vos appels n\'utilisent pas le budget inclus. Elle est stockée chiffrée et n\'est plus jamais affichée.',
        'label' => 'Clé API',
        'placeholder' => 'sk-ant-…',
        'current' => 'Clé enregistrée : :hint',
        'save' => 'Enregistrer la clé',
        'remove' => 'Supprimer la clé',
        'remove_confirm' => 'Supprimer votre clé Anthropic ?',
    ],
    'key_saved' => 'Clé enregistrée.',
    'key_removed' => 'Clé supprimée.',
    'errors' => [
        'disabled' => 'L\'IA est désactivée.',
        'no_provider' => 'Aucun fournisseur d\'IA n\'est configuré.',
        'budget_exceeded' => 'Vous avez épuisé le budget d\'IA de ce mois-ci. Vous pouvez ajouter votre propre clé Anthropic dans Paramètres › IA.',
        'rate_limited' => 'Trop de requêtes d\'IA. Veuillez patienter un instant.',
        'failed' => 'Les fournisseurs d\'IA n\'ont pas répondu correctement. Veuillez réessayer plus tard.',
    ],
];
