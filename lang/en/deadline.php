<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'A deadline is coming up',
        'body' => '{0} Your deadline at :company (:title) is today.|{1} Your deadline at :company (:title) is tomorrow.|[2,*] Your deadline at :company (:title) is in :days days.',
    ],
    'mail' => [
        'subject' => 'Upcoming deadline: :company',
        'heading' => 'Upcoming deadline at :company',
        'intro' => 'Your deadline for :title is :date.',
        'open' => 'Open my application',
        'reminder' => 'You set this date yourself; we never contact the company.',
    ],
];
