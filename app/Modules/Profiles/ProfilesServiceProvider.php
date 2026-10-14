<?php

declare(strict_types=1);

namespace App\Modules\Profiles;

use App\Modules\Identity\Application\Events\ConsentRevoked;
use App\Modules\Profiles\Application\Actions\ExtractCvText;
use App\Modules\Profiles\Application\Listeners\DeleteCvsWhenConsentRevoked;
use App\Modules\Profiles\Application\Privacy\ProfileDataContributor;
use App\Modules\Profiles\Infrastructure\Cv\DocxTextExtractor;
use App\Modules\Profiles\Infrastructure\Cv\PdfTextExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class ProfilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocxTextExtractor::class, fn (): DocxTextExtractor => new DocxTextExtractor(
            (int) config('privacy.cv.max_uncompressed_bytes'),
        ));
        $this->app->tag([PdfTextExtractor::class, DocxTextExtractor::class], 'cv.extractors');
        $this->app->when(ExtractCvText::class)->needs('$extractors')->giveTagged('cv.extractors');

        $this->app->tag([ProfileDataContributor::class], 'personal-data');
    }

    public function boot(): void
    {
        Event::listen(ConsentRevoked::class, DeleteCvsWhenConsentRevoked::class);

        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perHour(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
