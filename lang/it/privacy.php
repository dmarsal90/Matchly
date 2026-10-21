<?php

declare(strict_types=1);

return [
    'title' => 'Privacy e dati',
    'description' => 'Scegli cosa trattiamo ed esercita i tuoi diritti.',
    'consents_title' => 'Consensi',
    'consents_description' => 'Ogni uso facoltativo dei tuoi dati richiede la tua autorizzazione esplicita. Puoi revocarla in qualsiasi momento.',
    'granted_since' => 'Concesso il :date',
    'consent_saved' => 'Preferenza salvata.',
    'purposes' => [
        'cv_processing' => [
            'title' => 'Elabora il mio CV',
            'description' => 'Estrai testo, esperienza e competenze dai CV che carichi.',
            'revoke_warning' => 'La revoca elimina tutti i tuoi CV.',
        ],
        'github_import' => [
            'title' => 'Importa da GitHub',
            'description' => 'Analizza i tuoi repository pubblici per rilevare le tecnologie.',
            'revoke_warning' => 'La revoca elimina i dati importati da GitHub.',
        ],
        'linkedin_import' => [
            'title' => 'Importa da LinkedIn',
            'description' => 'Leggi i file del profilo della tua esportazione dati di LinkedIn.',
            'revoke_warning' => 'La revoca elimina i dati importati da LinkedIn.',
        ],
        'portfolio_import' => [
            'title' => 'Importa dal mio portfolio',
            'description' => 'Leggi il tuo sito web pubblico per rilevare le tecnologie.',
            'revoke_warning' => 'La revoca eliminerà i dati importati dal tuo portfolio.',
        ],
        'ai_processing' => [
            'title' => 'Usa l\'IA con i miei dati',
            'description' => 'Invia il tuo profilo a fornitori di IA per generare lettere di presentazione e CV su misura.',
            'revoke_warning' => 'Smetteremo di usare l\'IA con i tuoi dati.',
        ],
        'email_alerts' => [
            'title' => 'Avvisi via e-mail',
            'description' => 'Ricevi e-mail con le offerte corrispondenti alle tue ricerche salvate.',
            'revoke_warning' => 'Non riceverai più e-mail di avviso.',
        ],
    ],
    'export_title' => 'Scarica i miei dati',
    'export_description' => 'Uno ZIP con tutto ciò che conserviamo su di te (JSON leggibile) e i tuoi file originali.',
    'export_button' => 'Scarica lo ZIP',
    'delete_title' => 'Elimina il mio account',
    'delete_description' => 'Elimina definitivamente il tuo account e tutti i tuoi dati da Impostazioni › Account.',
    'delete_link' => 'Vai all\'eliminazione dell\'account',
    'policy_link' => 'Informativa sulla privacy',
    'version' => 'Versione dell\'informativa: :version',
];
