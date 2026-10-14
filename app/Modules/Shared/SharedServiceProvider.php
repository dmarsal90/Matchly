<?php

declare(strict_types=1);

namespace App\Modules\Shared;

use App\Modules\Shared\Domain\Contracts\HtmlSanitizer;
use App\Modules\Shared\Infrastructure\Html\PurifierHtmlSanitizer;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HtmlSanitizer::class, fn (): HtmlSanitizer => new PurifierHtmlSanitizer(
            storage_path('framework/cache/htmlpurifier'),
        ));
    }
}
