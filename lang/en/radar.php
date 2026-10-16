<?php

declare(strict_types=1);

return [
    'title' => 'Company radar',
    'description' => 'Follow companies and we tell you when a new job from them shows up among the ones we collect. We do not visit their websites: we only look at the jobs that already reach the app.',
    'list' => 'Companies you follow',
    'empty' => 'You do not follow any company yet. Click “Follow company” on any job.',
    'since' => 'Since :date',
    'last' => 'Last notice: :date',
    'never' => 'No notices yet',
    'unfollow' => 'Unfollow',
    'search' => 'See their jobs',
    'follow' => 'Follow company',
    'following' => 'Following this company',
    'manage' => 'Companies you follow',
    'too_many' => 'You already follow :max companies. Unfollow one to add another.',
    'notification' => [
        'title' => 'New jobs from a company you follow',
        'body' => '{1} :company has :count new job: :titles|[2,*] :company has :count new jobs: :titles',
    ],
    'mail' => [
        'subject' => '{1} :company has a new job|[2,*] :company has :count new jobs',
        'heading' => '{1} A new job at :company|[2,*] :count new jobs at :company',
        'more' => '{1} And :count more.|[2,*] And :count more.',
        'why' => 'You are getting this because you follow :company.',
        'manage' => 'Manage the companies you follow',
    ],
];
