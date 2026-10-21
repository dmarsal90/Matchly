<?php

declare(strict_types=1);

return [
    'title' => 'Datenschutz und Daten',
    'description' => 'Entscheide, was wir verarbeiten, und übe deine Rechte aus.',
    'consents_title' => 'Einwilligungen',
    'consents_description' => 'Jede optionale Verwendung deiner Daten braucht deine ausdrückliche Erlaubnis. Du kannst sie jederzeit widerrufen.',
    'granted_since' => 'Erteilt am :date',
    'consent_saved' => 'Einstellung gespeichert.',
    'purposes' => [
        'cv_processing' => [
            'title' => 'Meinen Lebenslauf verarbeiten',
            'description' => 'Text, Erfahrung und Kompetenzen aus den hochgeladenen Lebensläufen extrahieren.',
            'revoke_warning' => 'Der Widerruf löscht alle deine Lebensläufe.',
        ],
        'github_import' => [
            'title' => 'Von GitHub importieren',
            'description' => 'Deine öffentlichen Repositories analysieren, um Technologien zu erkennen.',
            'revoke_warning' => 'Der Widerruf löscht die von GitHub importierten Daten.',
        ],
        'linkedin_import' => [
            'title' => 'Von LinkedIn importieren',
            'description' => 'Die Profildateien deines LinkedIn-Datenexports lesen.',
            'revoke_warning' => 'Der Widerruf löscht die von LinkedIn importierten Daten.',
        ],
        'portfolio_import' => [
            'title' => 'Aus meinem Portfolio importieren',
            'description' => 'Deine öffentliche Website lesen, um Technologien zu erkennen.',
            'revoke_warning' => 'Der Widerruf löscht die aus deinem Portfolio importierten Daten.',
        ],
        'ai_processing' => [
            'title' => 'KI mit meinen Daten nutzen',
            'description' => 'Dein Profil an KI-Anbieter senden, um maßgeschneiderte Anschreiben und Lebensläufe zu erzeugen.',
            'revoke_warning' => 'Wir nutzen die KI nicht mehr mit deinen Daten.',
        ],
        'email_alerts' => [
            'title' => 'E-Mail-Benachrichtigungen',
            'description' => 'E-Mails mit Stellen erhalten, die zu deinen gespeicherten Suchen passen.',
            'revoke_warning' => 'Du erhältst keine E-Mails zu Suchaufträgen mehr.',
        ],
    ],
    'export_title' => 'Meine Daten herunterladen',
    'export_description' => 'Ein ZIP mit allem, was wir über dich speichern (lesbares JSON), und deinen Originaldateien.',
    'export_button' => 'ZIP herunterladen',
    'delete_title' => 'Mein Konto löschen',
    'delete_description' => 'Lösche dein Konto und alle deine Daten dauerhaft unter Einstellungen › Konto.',
    'delete_link' => 'Zur Kontolöschung',
    'policy_link' => 'Datenschutzerklärung',
    'version' => 'Version der Erklärung: :version',
];
