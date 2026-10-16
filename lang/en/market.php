<?php

declare(strict_types=1);

return [
    'title' => 'Market by technology',
    'description' => 'How much demand and pay there is for your technologies and the ones you lack, among the :total open jobs we collect.',
    'empty' => 'No data yet: complete your skills profile and wait for jobs with tags to arrive.',
    'table' => 'Demand, trend and pay by technology',
    'skill' => 'Technology',
    'jobs' => 'Jobs',
    'trend' => 'Trend',
    'pay' => 'Annual pay (median and range)',
    'yours' => 'Yours',
    'missing' => 'Missing',
    'sample' => ':count with pay',
    'no_pay' => 'Few jobs state a salary (minimum :min)',
    'note' => 'Jobs are counted by the tags the sources give, so a technology under another name can come up short. The trend compares its weight among new jobs of the last 30 days with the 30 before, and is only shown with enough data. It is a rough reference from our own database, not a market study.',
    'trends' => [
        'up' => 'Rising',
        'down' => 'Falling',
        'flat' => 'Steady',
        'unknown' => 'Not enough data',
    ],
];
