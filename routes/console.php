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
