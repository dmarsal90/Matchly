<?php

declare(strict_types=1);

namespace App\Modules\Applications;

use App\Modules\Applications\Application\Privacy\ApplicationDataContributor;
use App\Modules\Applications\Domain\Contracts\ApplicationAssistant;
use App\Modules\Applications\Infrastructure\Assistant\PackageFormFields;
use App\Modules\Applications\Infrastructure\Console\SendDeadlineRemindersCommand;
use App\Modules\Applications\Infrastructure\Console\SendFollowUpRemindersCommand;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class ApplicationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ApplicationDataContributor::class], 'personal-data');
        $this->app->bind(ApplicationAssistant::class, PackageFormFields::class);
    }

    public function boot(): void
    {
        // Each package can make two AI calls (CV and letter).
        if ($this->app->runningInConsole()) {
            $this->commands([SendFollowUpRemindersCommand::class, SendDeadlineRemindersCommand::class]);
        }

        RateLimiter::for('quick-apply', fn (Request $request): Limit => Limit::perHour(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
