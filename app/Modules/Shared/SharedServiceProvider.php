<?php

declare(strict_types=1);

namespace App\Modules\Shared;

use App\Modules\Shared\Domain\Contracts\HtmlSanitizer;
use App\Modules\Shared\Infrastructure\Files\EncryptedFileStore;
use App\Modules\Shared\Infrastructure\Html\PurifierHtmlSanitizer;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HtmlSanitizer::class, fn (): HtmlSanitizer => new PurifierHtmlSanitizer(
            storage_path('framework/cache/htmlpurifier'),
        ));

        $this->app->bind(EncryptedFileStore::class, fn (Application $app): EncryptedFileStore => new EncryptedFileStore(
            $app->make(Filesystems::class),
            $app->make(Encrypter::class),
            (string) config('privacy.files_disk'),
        ));
    }
}
