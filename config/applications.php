<?php

declare(strict_types=1);

return [
    // Days after applying with no answer before the person is reminded to follow up.
    'follow_up_days' => (int) env('FOLLOW_UP_DAYS', 10),
];
