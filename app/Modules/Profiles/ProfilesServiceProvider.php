<?php

declare(strict_types=1);

namespace App\Modules\Profiles;

use App\Modules\Identity\Application\Events\ConsentRevoked;
use App\Modules\Profiles\Application\Actions\ExtractCvText;
use App\Modules\Profiles\Application\Actions\ImportLinkedInExport;
use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Application\Listeners\DeleteCvsWhenConsentRevoked;
use App\Modules\Profiles\Application\Listeners\DeleteImportedDataWhenConsentRevoked;
use App\Modules\Profiles\Application\Listeners\RefreshSearchProfile;
use App\Modules\Profiles\Application\Privacy\ProfileDataContributor;
use App\Modules\Profiles\Application\Privacy\SkillsDataContributor;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Profiles\Domain\Services\SkillMerger;
use App\Modules\Profiles\Infrastructure\Cv\DocxTextExtractor;
use App\Modules\Profiles\Infrastructure\Cv\PdfTextExtractor;
use App\Modules\Profiles\Infrastructure\GitHub\GitHubClient;
use App\Modules\Profiles\Infrastructure\GitHub\GitHubProfileReader;
use App\Modules\Profiles\Infrastructure\GitHub\ManifestParser;
use App\Modules\Profiles\Infrastructure\LinkedIn\LinkedInExportMapper;
use App\Modules\Profiles\Infrastructure\LinkedIn\SafeZipReader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
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

        $this->registerSkills();
        $this->registerGitHub();
        $this->registerLinkedIn();

        $this->app->tag([ProfileDataContributor::class, SkillsDataContributor::class], 'personal-data');
    }

    public function boot(): void
    {
        Event::listen(ConsentRevoked::class, DeleteCvsWhenConsentRevoked::class);
        Event::listen(ConsentRevoked::class, DeleteImportedDataWhenConsentRevoked::class);
        Event::listen(ProfileChanged::class, RefreshSearchProfile::class);

        RateLimiter::for('imports', fn (Request $request): Limit => Limit::perHour(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perHour(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    private function registerSkills(): void
    {
        $this->app->singleton(SkillCatalog::class, fn (): SkillCatalog => new SkillCatalog(
            (array) config('skills.catalog', []),
            (array) config('skills.packages', []),
        ));
        $this->app->bind(SkillMerger::class, fn (): SkillMerger => new SkillMerger((array) config('skills.confidence', [])));
    }

    private function registerGitHub(): void
    {
        $this->app->bind(GitHubClient::class, fn (Application $app): GitHubClient => new GitHubClient(
            $app->make(Http::class),
            $app->make(Cache::class),
            (string) config('github.api_url'),
            filled(config('github.token')) ? (string) config('github.token') : null,
            (int) config('github.timeout'),
            (int) config('github.cache_ttl_seconds'),
            (string) config('app.name').' ('.config('app.url').')',
        ));

        $this->app->bind(GitHubProfileReader::class, fn (Application $app): GitHubProfileReader => new GitHubProfileReader(
            client: $app->make(GitHubClient::class),
            catalog: $app->make(SkillCatalog::class),
            manifestParser: new ManifestParser,
            manifests: (array) config('github.manifests', []),
            markerFiles: (array) config('github.marker_files', []),
            ignoredLanguages: array_values((array) config('skills.ignored_languages', [])),
            recentMonths: (int) config('github.recent_months'),
            maxRepositories: (int) config('github.max_repositories'),
            anonymousMaxRepositories: (int) config('github.anonymous_max_repositories'),
            readmeRepositories: (int) config('github.readme_repositories'),
            readmeMaxChars: (int) config('github.readme_max_chars'),
            maxManifestBytes: (int) config('github.max_manifest_bytes'),
            minLanguageShare: (float) config('github.min_language_share'),
        ));
    }

    private function registerLinkedIn(): void
    {
        $this->app->bind(SafeZipReader::class, fn (): SafeZipReader => new SafeZipReader(
            maxEntries: (int) config('linkedin.max_entries'),
            maxUncompressedBytes: (int) config('linkedin.max_uncompressed_bytes'),
            maxFileBytes: (int) config('linkedin.max_file_bytes'),
            maxCompressionRatio: (int) config('linkedin.max_compression_ratio'),
        ));
        $this->app->bind(LinkedInExportMapper::class, fn (Application $app): LinkedInExportMapper => new LinkedInExportMapper(
            $app->make(SkillCatalog::class),
            (int) config('linkedin.max_rows'),
        ));
        $this->app->when(ImportLinkedInExport::class)->needs('$whitelist')->give(fn (): array => (array) config('linkedin.files', []));
    }
}
