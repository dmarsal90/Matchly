<?php

declare(strict_types=1);

namespace App\Modules\Alerts;

use App\Modules\Alerts\Application\Listeners\MatchIngestedJobsToAlerts;
use App\Modules\Alerts\Application\Privacy\AlertsDataContributor;
use App\Modules\Alerts\Infrastructure\Console\SendDigestsCommand;
use App\Modules\Alerts\Infrastructure\Console\SendRadarCommand;
use App\Modules\Jobs\Application\Events\JobsIngested;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AlertsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([AlertsDataContributor::class], 'personal-data');
    }

    public function boot(): void
    {
        Event::listen(JobsIngested::class, MatchIngestedJobsToAlerts::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SendDigestsCommand::class, SendRadarCommand::class]);
        }
    }
}
