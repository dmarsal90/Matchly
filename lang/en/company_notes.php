<?php

declare(strict_types=1);

return [
    'title' => 'What I know about :company',
    'description' => 'Facts you know about the company. Your cover letter and interview pack can use them to show real interest. Only what you write here is ever mentioned, nothing is invented.',
    'empty' => 'No notes yet. Add what draws you to the company: its product, a recent launch, its values.',
    'fact' => 'What you know',
    'fact_placeholder' => 'e.g. They launched an open-source SDK last year',
    'source' => 'Source (link, optional)',
    'source_placeholder' => 'https://…',
    'add' => 'Add note',
    'approve' => 'Use it',
    'discard' => 'Discard',
    'suggestions' => 'Suggestions to review',
    'suggestions_hint' => 'Read each one and keep only what is true and relevant. Nothing here is used until you approve it.',
    'approved_title' => 'Ready to use',
    'delete' => 'Delete note',
    'delete_confirm' => 'Delete this note?',
    'origin' => ['manual' => 'Written by you', 'web' => 'Found on their website'],
    'pending' => 'Waiting for your approval',
    'source_link' => 'Source',
    'discover' => [
        'title' => 'Look it up on their website',
        'hint' => 'We read their public pages (home, about, careers), respecting robots.txt, and suggest what they say about themselves. Nothing is used until you approve it.',
        'url' => 'Company website',
        'url_placeholder' => 'https://company.com',
        'submit' => 'Find facts',
        'found' => '{1} :count suggestion found. Review it below.|[2,*] :count suggestions found. Review them below.',
        'nothing' => 'We read the site but found nothing worth suggesting.',
        'unreachable' => 'We could not read that website (it may block robots or be unreachable).',
    ],
];
