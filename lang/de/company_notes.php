<?php

declare(strict_types=1);

return [
    'title' => 'Was ich über :company weiß',
    'description' => 'Fakten, die du über das Unternehmen kennst. Dein Anschreiben und dein Gesprächspaket können sie nutzen, um echtes Interesse zu zeigen. Erwähnt wird nur, was du hier schreibst, nichts wird erfunden.',
    'empty' => 'Noch keine Notizen. Füge hinzu, was dich am Unternehmen reizt: sein Produkt, einen aktuellen Launch, seine Werte.',
    'fact' => 'Was du weißt',
    'fact_placeholder' => 'z. B. Sie haben letztes Jahr ein Open-Source-SDK veröffentlicht',
    'source' => 'Quelle (Link, optional)',
    'source_placeholder' => 'https://…',
    'add' => 'Notiz hinzufügen',
    'approve' => 'Verwenden',
    'discard' => 'Verwerfen',
    'suggestions' => 'Vorschläge zur Prüfung',
    'suggestions_hint' => 'Lies jeden einzelnen und behalte nur, was wahr und relevant ist. Nichts davon wird verwendet, bevor du es freigibst.',
    'approved_title' => 'Bereit zur Verwendung',
    'delete' => 'Notiz löschen',
    'delete_confirm' => 'Diese Notiz löschen?',
    'origin' => ['manual' => 'Von dir geschrieben', 'web' => 'Auf der Website gefunden'],
    'pending' => 'Wartet auf deine Freigabe',
    'source_link' => 'Quelle',
    'discover' => [
        'title' => 'Auf der Website nachschlagen',
        'hint' => 'Wir lesen die öffentlichen Seiten (Startseite, Über uns, Karriere), beachten dabei robots.txt und schlagen vor, was das Unternehmen über sich sagt. Nichts wird verwendet, bevor du es freigibst.',
        'url' => 'Website des Unternehmens',
        'url_placeholder' => 'https://unternehmen.de',
        'submit' => 'Fakten finden',
        'found' => '{1} :count Vorschlag gefunden. Prüfe ihn unten.|[2,*] :count Vorschläge gefunden. Prüfe sie unten.',
        'nothing' => 'Wir haben die Website gelesen, aber nichts gefunden, was sich vorzuschlagen lohnt.',
        'unreachable' => 'Wir konnten diese Website nicht lesen (sie blockiert möglicherweise Roboter oder ist nicht erreichbar).',
    ],
];
