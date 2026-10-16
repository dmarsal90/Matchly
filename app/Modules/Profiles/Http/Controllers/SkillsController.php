<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Controllers;

use App\Modules\Identity\Domain\Enums\OAuthProvider;
use App\Modules\Identity\Http\Controllers\OAuthController;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Profiles\Application\Queries\SkillProfileQuery;
use App\Modules\Profiles\Domain\Data\MergedSkill;
use App\Modules\Profiles\Domain\Enums\CvStatus;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Profiles\Http\Requests\UpdateProfileRequest;
use App\Modules\Profiles\Http\Resources\ProfileImportResource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The merged skill profile, its sources and the search profile built from it. */
final class SkillsController
{
    public function index(Request $request, #[CurrentUser] User $user, SkillProfileQuery $skills, SkillCatalog $catalog): Response
    {
        $profile = $user->profile()->firstOrNew();
        $merged = $skills->for($user->id);
        $github = $user->socialAccounts()->where('provider', OAuthProvider::Github)->first();

        return Inertia::render('skills/Index', [
            'skills' => array_map(fn (MergedSkill $skill): array => $skill->toArray(), $merged),
            'sources' => fn (): array => $this->sources($user),
            'onboardingCompleted' => $profile->onboarding_completed_at !== null,
            'github' => [
                'linked_username' => $github?->nickname,
                'suggested_username' => $profile->github_username ?? $github?->nickname,
                'oauth_available' => in_array(OAuthProvider::Github->value, OAuthController::enabledProviders(), true),
            ],
            'portfolio' => ['suggested_url' => $profile->portfolio_url],
            'cv' => [
                'ready' => $user->cvDocuments()->where('is_primary', true)->where('status', CvStatus::Extracted)->exists(),
            ],
            'searchProfile' => $this->searchProfile($user, $merged, $catalog),
            'options' => Inertia::once(fn (): array => [
                'roles' => array_column(JobRole::cases(), 'value'),
                'seniorities' => array_column(Seniority::cases(), 'value'),
                'workModes' => array_column(WorkMode::cases(), 'value'),
                'employmentTypes' => array_column(EmploymentType::cases(), 'value'),
                'currencies' => UpdateProfileRequest::CURRENCIES,
                'skillSuggestions' => $catalog->labels(),
                'linkedinMaxKilobytes' => (int) config('linkedin.max_upload_kilobytes'),
                'linkedinExportUrl' => (string) config('linkedin.export_url'),
            ]),
        ]);
    }

    /**
     * Per source: skills it contributes, the confirmed import and any import in flight.
     *
     * @return array<string, array<string, mixed>>
     */
    private function sources(User $user): array
    {
        $counts = $user->skills()->toBase()->selectRaw('source, COUNT(*) AS total')->groupBy('source')->pluck('total', 'source');
        $imports = $user->profileImports()->latest('id')->get()->groupBy(fn (ProfileImport $import): string => $import->source->value);

        $sources = [];
        foreach (SkillSource::cases() as $source) {
            /** @var \Illuminate\Support\Collection<int, ProfileImport> $own */
            $own = $imports->get($source->value, collect());
            $confirmed = $own->first(fn (ProfileImport $import): bool => $import->status === ImportStatus::Confirmed);
            $pending = $own->first(fn (ProfileImport $import): bool => $import->status !== ImportStatus::Confirmed);
            $purpose = $source->consentPurpose();

            $sources[$source->value] = [
                'skills_count' => (int) ($counts[$source->value] ?? 0),
                'confirmed' => $confirmed !== null ? (new ProfileImportResource($confirmed))->resolve() : null,
                'pending' => $pending !== null ? (new ProfileImportResource($pending))->resolve() : null,
                'consented' => $purpose === null || $user->hasConsented($purpose),
            ];
        }

        return $sources;
    }

    /**
     * @param  list<MergedSkill>  $merged
     * @return array<string, mixed>|null
     */
    private function searchProfile(User $user, array $merged, SkillCatalog $catalog): ?array
    {
        $searchProfile = $user->profile?->searchProfile();
        if ($searchProfile === null) {
            return null;
        }

        $names = array_column(array_map(fn (MergedSkill $skill): array => ['skill' => $skill->skill, 'name' => $skill->name], $merged), 'name', 'skill');

        return [
            ...$searchProfile->toArray(),
            'technology_names' => array_map(fn (string $slug): string => $names[$slug] ?? $catalog->label($slug, $slug), $searchProfile->technologies),
            'listing_query' => $searchProfile->toListingQuery(),
        ];
    }
}
