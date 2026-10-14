<?php

declare(strict_types=1);

namespace App\Modules\Jobs;

use App\Modules\Jobs\Domain\Contracts\JobRepository;
use App\Modules\Jobs\Domain\Contracts\LinkChecker;
use App\Modules\Jobs\Domain\Contracts\WorkModeClassifier;
use App\Modules\Jobs\Infrastructure\Classifiers\RegionDictionary;
use App\Modules\Jobs\Infrastructure\Console\CheckJobLinksCommand;
use App\Modules\Jobs\Infrastructure\Console\CloseExpiredJobsCommand;
use App\Modules\Jobs\Infrastructure\Http\HttpLinkChecker;
use App\Modules\Jobs\Infrastructure\Persistence\EloquentJobRepository;
use App\Modules\Jobs\Infrastructure\Search\JobTextSearch;
use App\Modules\Jobs\Infrastructure\Search\LikeJobTextSearch;
use App\Modules\Jobs\Infrastructure\Search\PostgresJobTextSearch;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class JobsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(JobRepository::class, EloquentJobRepository::class);
        $this->app->bind(LinkChecker::class, HttpLinkChecker::class);
        $this->app->bind(JobTextSearch::class, fn (Application $app): JobTextSearch => $app->make('db')->connection()->getDriverName() === 'pgsql'
            ? new PostgresJobTextSearch
            : new LikeJobTextSearch);
        // The dictionary builds its country-name index once per process.
        $this->app->singleton(RegionDictionary::class);
        $this->app->bind(
            WorkModeClassifier::class,
            fn (Application $app): WorkModeClassifier => $app->make((string) config('jobs.classifier')),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CloseExpiredJobsCommand::class, CheckJobLinksCommand::class]);
        }
    }
}
