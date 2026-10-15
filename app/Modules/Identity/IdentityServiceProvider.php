<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Application\Actions\DeleteAccount;
use App\Modules\Identity\Application\Actions\ExportPersonalData;
use App\Modules\Identity\Application\Privacy\AccountDataContributor;
use App\Modules\Identity\Infrastructure\Console\MakeAdminCommand;
use App\Modules\Identity\Infrastructure\Console\PrunePersonalDataCommand;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Other modules tag their own contributors with 'personal-data'.
        $this->app->tag([AccountDataContributor::class], 'personal-data');
        $this->app->when([ExportPersonalData::class, DeleteAccount::class])->needs('$contributors')->giveTagged('personal-data');
    }

    public function boot(): void
    {
        RateLimiter::for('oauth', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('exports', fn (Request $request): Limit => Limit::perHour(3)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([PrunePersonalDataCommand::class, MakeAdminCommand::class]);
        }
    }
}
