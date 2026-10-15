<?php

declare(strict_types=1);

namespace App\Modules\Matching;

use App\Modules\Jobs\Application\Events\JobsIngested;
use App\Modules\Matching\Application\Listeners\PrepareIngestedJobs;
use App\Modules\Matching\Application\Listeners\RecalculateMatchesOnProfileChange;
use App\Modules\Matching\Application\Privacy\MatchDataContributor;
use App\Modules\Matching\Domain\Services\MatchScorer;
use App\Modules\Matching\Infrastructure\Console\ExtractRequirementsCommand;
use App\Modules\Matching\Infrastructure\Console\RecalculateMatchesCommand;
use App\Modules\Profiles\Application\Events\ProfileChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class MatchingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([MatchDataContributor::class], 'personal-data');

        $this->app->bind(MatchScorer::class, fn (): MatchScorer => new MatchScorer(
            weights: (array) config('matching.weights', []),
            levels: ['high' => (int) config('matching.levels.high', 75), 'medium' => (int) config('matching.levels.medium', 50)],
            requiredShare: (float) config('matching.required_share', 0.75),
            declaredOnlyFactor: (float) config('matching.declared_only_factor', 0.8),
            seniorityYears: (array) config('matching.seniority_years', []),
        ));
    }

    public function boot(): void
    {
        Event::listen(ProfileChanged::class, RecalculateMatchesOnProfileChange::class);
        Event::listen(JobsIngested::class, PrepareIngestedJobs::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RecalculateMatchesCommand::class, ExtractRequirementsCommand::class]);
        }
    }
}
