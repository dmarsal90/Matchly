<?php

declare(strict_types=1);

namespace App\Modules\Providers;

use App\Modules\Providers\Domain\Contracts\AtsCompanyDirectory;
use App\Modules\Providers\Domain\Contracts\IngestionLog;
use App\Modules\Providers\Infrastructure\Console\IngestJobsCommand;
use App\Modules\Providers\Infrastructure\Console\ListSourcesCommand;
use App\Modules\Providers\Infrastructure\Persistence\EloquentAtsCompanyDirectory;
use App\Modules\Providers\Infrastructure\Persistence\EloquentIngestionLog;
use Illuminate\Support\ServiceProvider;

final class ProvidersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IngestionLog::class, EloquentIngestionLog::class);
        $this->app->bind(AtsCompanyDirectory::class, EloquentAtsCompanyDirectory::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([IngestJobsCommand::class, ListSourcesCommand::class]);
        }
    }
}
