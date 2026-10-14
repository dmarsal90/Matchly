<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Privacy;

use App\Modules\Identity\Domain\Contracts\PersonalDataContributor;
use App\Modules\Identity\Domain\Data\PersonalData;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Profiles\Infrastructure\Models\Profile;
use App\Modules\Shared\Infrastructure\Files\EncryptedFileStore;

final readonly class ProfileDataContributor implements PersonalDataContributor
{
    public function __construct(private EncryptedFileStore $files) {}

    public function export(int $userId): PersonalData
    {
        $profile = Profile::query()->where('user_id', $userId)->first();
        $documents = CvDocument::query()->where('user_id', $userId)->orderBy('id')->get();

        $files = [];
        foreach ($documents as $document) {
            $files["{$document->id}-{$document->original_name}"] = $this->files->get($document->path);
        }

        return new PersonalData('profile', [
            'profile' => $profile?->only([
                'headline', 'phone', 'country', 'city', 'github_username', 'linkedin_url', 'portfolio_url',
                'languages', 'salary_min', 'salary_currency', 'salary_period', 'preferences',
                'target_roles', 'seniority', 'years_experience', 'regions', 'utc_offset_min', 'utc_offset_max',
                'dealbreakers', 'dealbreaker_notes', 'search_profile', 'onboarding_completed_at',
            ]),
            'cv_documents' => $documents->map(fn (CvDocument $document): array => [
                'id' => $document->id,
                'file' => "files/profile/{$document->id}-{$document->original_name}",
                'uploaded_at' => $document->created_at->toIso8601String(),
                'is_primary' => $document->is_primary,
                'extracted_text' => $document->extracted_text,
                'parsed_data' => $document->parsed_data,
            ])->values()->all(),
        ], $files);
    }

    public function erase(int $userId): void
    {
        $this->files->deleteDirectory("users/{$userId}");
    }
}
