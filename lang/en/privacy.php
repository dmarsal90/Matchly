<?php

declare(strict_types=1);

return [
    'title' => 'Privacy & data',
    'description' => 'Choose what we process and exercise your rights.',
    'consents_title' => 'Consents',
    'consents_description' => 'Every optional use of your data needs your explicit permission. You can withdraw it at any time.',
    'granted_since' => 'Granted on :date',
    'consent_saved' => 'Preference saved.',
    'purposes' => [
        'cv_processing' => [
            'title' => 'Process my CV',
            'description' => 'Extract text, experience and skills from the CVs you upload.',
            'revoke_warning' => 'Withdrawing deletes all your CVs.',
        ],
        'github_import' => [
            'title' => 'Import from GitHub',
            'description' => 'Analyse your public repositories to detect technologies.',
            'revoke_warning' => 'Withdrawing deletes the data imported from GitHub.',
        ],
        'linkedin_import' => [
            'title' => 'Import from LinkedIn',
            'description' => 'Read the profile files of your LinkedIn data export.',
            'revoke_warning' => 'Withdrawing deletes the data imported from LinkedIn.',
        ],
        'portfolio_import' => [
            'title' => 'Import from my portfolio',
            'description' => 'Read your public website to detect technologies.',
            'revoke_warning' => 'Withdrawing will delete the data imported from your portfolio.',
        ],
        'ai_processing' => [
            'title' => 'Use AI with my data',
            'description' => 'Send your profile to AI providers to generate tailored cover letters and CVs.',
            'revoke_warning' => 'We will stop using AI with your data.',
        ],
        'email_alerts' => [
            'title' => 'E-mail alerts',
            'description' => 'Receive e-mails with jobs matching your saved searches.',
            'revoke_warning' => 'You will stop receiving alert e-mails.',
        ],
    ],
    'export_title' => 'Download my data',
    'export_description' => 'A ZIP with everything we store about you (readable JSON) and your original files.',
    'export_button' => 'Download ZIP',
    'delete_title' => 'Delete my account',
    'delete_description' => 'Permanently delete your account and all your data from Settings › Account.',
    'delete_link' => 'Go to account deletion',
    'policy_link' => 'Privacy policy',
    'version' => 'Policy version: :version',
];
