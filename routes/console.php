<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Ingestion: checked often, but each source only runs when its own interval
// (config/jobs.php) and failure back-off allow it.
Schedule::command('jobs:ingest')->everyTenMinutes()->withoutOverlapping()->onOneServer();

Schedule::command('jobs:close-expired')->hourly()->onOneServer();

Schedule::command('jobs:check-links')->dailyAt('03:30')->onOneServer();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('queue:prune-failed --hours=168')->daily();

Schedule::command('privacy:prune')->dailyAt('04:00')->onOneServer();

// Saved-search digests (immediate alerts are sent as jobs arrive).
Schedule::command('alerts:digest daily')->dailyAt('08:00')->onOneServer();
Schedule::command('alerts:digest weekly')->weeklyOn(1, '08:00')->onOneServer();

// Applications sent N days ago with no answer: remind once, with a draft message.
Schedule::command('applications:follow-up')->dailyAt('09:00')->onOneServer();
Schedule::command('applications:deadlines')->dailyAt('09:00')->onOneServer();
