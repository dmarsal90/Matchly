<?php

declare(strict_types=1);

return [
    'title' => 'Künstliche Intelligenz',
    'description' => 'Die KI strukturiert deinen Lebenslauf, fasst dein GitHub zusammen und extrahiert die Anforderungen der Stellen. Sie erfindet nie Erfahrung: Du prüfst alles, bevor es gespeichert wird.',
    'status' => [
        'available' => 'Die KI ist verfügbar.',
        'unavailable' => 'Derzeit ist kein KI-Anbieter verfügbar. Du kannst deinen eigenen Anthropic-Schlüssel verwenden.',
    ],
    'consent' => [
        'given' => 'Du hast der Verarbeitung deiner Daten durch die KI zugestimmt.',
        'missing' => 'Du hast der Verarbeitung deiner Daten durch die KI noch nicht zugestimmt. Du wirst gefragt, wenn du eine KI-Funktion nutzt.',
        'manage' => 'In den Datenschutzeinstellungen verwalten',
    ],
    'usage' => [
        'title' => 'Nutzung in diesem Monat',
        'calls' => '{0} Keine Aufrufe|{1} :count Aufruf|[2,*] :count Aufrufe',
        'budget' => 'Inklusivbudget: :used von :limit USD',
        'own_key_cost' => 'Kosten für deinen Schlüssel: :cost USD (direkt von Anthropic abgerechnet).',
        'tasks' => [
            'extract_job_requirements' => 'Stellenanforderungen',
            'structure_cv' => 'Lebenslauf strukturieren',
            'summarize_github' => 'GitHub-Zusammenfassung',
            'tailor_cv' => 'Lebenslauf anpassen',
            'cover_letter' => 'Anschreiben',
        ],
    ],
    'key' => [
        'title' => 'Dein Anthropic-Schlüssel (optional)',
        'description' => 'Mit deinem eigenen Schlüssel verbrauchen deine Aufrufe nicht das Inklusivbudget. Er wird verschlüsselt gespeichert und nie wieder angezeigt.',
        'label' => 'API-Schlüssel',
        'placeholder' => 'sk-ant-…',
        'current' => 'Gespeicherter Schlüssel: :hint',
        'save' => 'Schlüssel speichern',
        'remove' => 'Schlüssel entfernen',
        'remove_confirm' => 'Deinen Anthropic-Schlüssel entfernen?',
    ],
    'key_saved' => 'Schlüssel gespeichert.',
    'key_removed' => 'Schlüssel entfernt.',
    'errors' => [
        'disabled' => 'Die KI ist ausgeschaltet.',
        'no_provider' => 'Es ist kein KI-Anbieter konfiguriert.',
        'budget_exceeded' => 'Du hast das KI-Budget dieses Monats aufgebraucht. Du kannst deinen eigenen Anthropic-Schlüssel unter Einstellungen › KI hinzufügen.',
        'rate_limited' => 'Zu viele KI-Anfragen. Bitte warte einen Moment.',
        'failed' => 'Die KI-Anbieter haben nicht richtig geantwortet. Bitte versuche es später erneut.',
    ],
];
