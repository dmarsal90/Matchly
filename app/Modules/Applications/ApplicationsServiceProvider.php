<?php

declare(strict_types=1);

namespace App\Modules\Applications;

use App\Modules\Applications\Application\Privacy\ApplicationDataContributor;
use Illuminate\Support\ServiceProvider;

final class ApplicationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ApplicationDataContributor::class], 'personal-data');
    }
}
