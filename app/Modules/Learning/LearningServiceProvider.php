<?php

declare(strict_types=1);

namespace App\Modules\Learning;

use App\Modules\Learning\Domain\Services\CourseCatalog;
use App\Modules\Learning\Infrastructure\Console\CheckCoursesCommand;
use Illuminate\Support\ServiceProvider;

final class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CourseCatalog::class, function (): CourseCatalog {
            /** @var array<string, list<array{0: string, 1: string, 2: string, 3: string}>> $catalog */
            $catalog = (array) config('courses.catalog', []);
            /** @var list<array{title: string, provider: string, url: string, price: string}> $search */
            $search = array_values((array) config('courses.search', []));

            return new CourseCatalog($catalog, $search);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CheckCoursesCommand::class]);
        }
    }
}
