<?php

declare(strict_types=1);

return [
    'title' => 'Insights',
    'description' => 'What your job search says about you. Computed from your saved matches, nothing leaves the app.',
    'skill_gaps' => [
        'title' => 'Skills to learn next',
        'description' => 'Skills your tracked jobs and best matches ask for and your profile lacks, ranked by how many jobs they would open up.',
        'basis' => 'Based on :jobs jobs (:tracked of them tracked by you).',
        'empty' => 'Nothing to show yet. Complete your skills profile and save a few jobs to see what is worth learning.',
        'next' => 'Start with :skill',
        'next_hint' => '{0} It is asked for in :jobs jobs.|{1} It is the only required skill you lack in :count job, and it is asked for in :jobs jobs.|[2,*] It is the only required skill you lack in :count jobs, and it is asked for in :jobs jobs.',
        'table' => 'Missing skills ranked',
        'skill' => 'Skill',
        'jobs' => 'Jobs',
        'required' => 'Required in',
        'only_gap' => 'Only gap in',
        'tracked' => 'Tracked',
    ],
];
