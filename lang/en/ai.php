<?php

declare(strict_types=1);

return [
    'title' => 'Artificial intelligence',
    'description' => 'AI structures your CV, summarises your GitHub and extracts job requirements. It never invents experience: you review everything before it is saved.',
    'status' => [
        'available' => 'AI is available.',
        'unavailable' => 'No AI provider is available right now. You can use your own Anthropic key.',
    ],
    'consent' => [
        'given' => 'You have consented to AI processing your data.',
        'missing' => 'You have not consented to AI processing your data yet. You will be asked when you use an AI feature.',
        'manage' => 'Manage in Privacy',
    ],
    'usage' => [
        'title' => 'Usage this month',
        'calls' => '{0} No calls|{1} :count call|[2,*] :count calls',
        'budget' => 'Included budget: :used of :limit USD',
        'own_key_cost' => 'Cost on your key: :cost USD (billed by Anthropic directly).',
        'tasks' => [
            'extract_job_requirements' => 'Job requirements',
            'structure_cv' => 'CV structuring',
            'summarize_github' => 'GitHub summary',
            'tailor_cv' => 'CV tailoring',
            'cover_letter' => 'Cover letter',
        ],
    ],
    'key' => [
        'title' => 'Your Anthropic key (optional)',
        'description' => 'With your own key, your calls do not use the included budget. It is stored encrypted and never shown again.',
        'label' => 'API key',
        'placeholder' => 'sk-ant-…',
        'current' => 'Saved key: :hint',
        'save' => 'Save key',
        'remove' => 'Remove key',
        'remove_confirm' => 'Remove your Anthropic key?',
    ],
    'key_saved' => 'Key saved.',
    'key_removed' => 'Key removed.',
    'errors' => [
        'disabled' => 'AI is turned off.',
        'no_provider' => 'No AI provider is configured.',
        'budget_exceeded' => 'You have used up this month\'s AI budget. You can add your own Anthropic key in Settings › AI.',
        'rate_limited' => 'Too many AI requests. Please wait a moment.',
        'failed' => 'The AI providers did not answer properly. Please try again later.',
    ],
];
