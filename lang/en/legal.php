<?php

declare(strict_types=1);

return [
    'back' => 'Back to home',
    'updated' => 'Last updated: :date',
    'privacy' => [
        'title' => 'Privacy policy',
        'intro' => ':app processes your data only to help you find remote work, with the safeguards of the GDPR.',
        'sections' => [
            'controller' => [
                'title' => 'Controller',
                'body' => 'The data controller is the owner of :app. Contact us about any privacy matter using the details on the site.',
            ],
            'data' => [
                'title' => 'What we process',
                'body' => 'Account data (name, e-mail), the career profile you enter, CVs you upload, data you import from GitHub or LinkedIn with your consent, and minimal technical data for security. We never ask for age, gender, nationality or a photo.',
            ],
            'purposes' => [
                'title' => 'Why',
                'body' => 'To show you jobs, score your fit, prepare applications and send alerts. Each optional processing relies on your consent, which you can withdraw in Settings › Privacy.',
            ],
            'security' => [
                'title' => 'Security',
                'body' => 'Files are stored encrypted, traffic uses HTTPS and only you can access your data.',
            ],
            'retention' => [
                'title' => 'Retention',
                'body' => 'Unverified accounts are deleted after :days days. Extracted CV text of inactive accounts is deleted after :months months. Deleting your account removes everything.',
            ],
            'rights' => [
                'title' => 'Your rights',
                'body' => 'You can access, correct, export and delete your data from Settings, and complain to your data protection authority.',
            ],
            'sources' => [
                'title' => 'Third-party jobs',
                'body' => 'Jobs come from public sources and link to their origin. When you apply, you deal directly with the company.',
            ],
        ],
    ],
    'terms' => [
        'title' => 'Terms of use',
        'intro' => ':app aggregates public remote job listings and helps you prepare applications.',
        'sections' => [
            'service' => [
                'title' => 'The service',
                'body' => 'Jobs belong to their original sources and are shown with attribution and a link. We do not guarantee their accuracy or availability.',
            ],
            'account' => [
                'title' => 'Your account',
                'body' => 'You are responsible for the accuracy of your profile and for keeping your credentials safe.',
            ],
            'ai' => [
                'title' => 'Generated content',
                'body' => 'Tailored cover letters and CVs only use your profile data. Always review them before sending.',
            ],
            'applications' => [
                'title' => 'Applications',
                'body' => 'We never apply on your behalf: you decide and apply on each job\'s official site.',
            ],
        ],
    ],
];
