<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application;

use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Queries\SkillProfileQuery;
use App\Modules\Profiles\Domain\Data\MergedSkill;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Shared\Infrastructure\Localization\Countries;

/**
 * The master CV: everything the person confirmed about themselves, and
 * nothing else. Experience and education come from the confirmed LinkedIn
 * import (or the confirmed CV import when there is none), skills from the
 * merged profile, contact data from the account and profile.
 */
final readonly class ResumeBuilder
{
    private const int MAX_SKILLS = 30;

    private const int MAX_BULLETS = 6;

    public function __construct(private SkillProfileQuery $skills) {}

    public function for(User $user, string $locale): ResumeData
    {
        $profile = $user->profile()->firstOrNew();
        $imports = ProfileImport::query()
            ->where('user_id', $user->id)
            ->where('status', ImportStatus::Confirmed)
            ->whereIn('source', [SkillSource::Linkedin, SkillSource::Cv])
            ->get()
            ->keyBy(fn (ProfileImport $import): string => $import->source->value);

        $primary = $imports->get(SkillSource::Linkedin->value)->payload ?? [];
        $secondary = $imports->get(SkillSource::Cv->value)->payload ?? [];
        $pick = fn (string $key): array => (array) (($primary[$key] ?? []) !== [] ? $primary[$key] : ($secondary[$key] ?? []));

        // Labels are stable keys (translated when shown).
        $links = array_values(array_filter([
            $profile->linkedin_url !== null ? ['label' => 'linkedin', 'url' => $profile->linkedin_url] : null,
            $profile->github_username !== null ? ['label' => 'github', 'url' => "https://github.com/{$profile->github_username}"] : null,
            $profile->portfolio_url !== null ? ['label' => 'portfolio', 'url' => $profile->portfolio_url] : null,
        ]));

        $country = $profile->country !== null ? (Countries::names($locale)[$profile->country] ?? $profile->country) : null;

        return ResumeData::fromArray([
            'contact' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $profile->phone,
                'location' => implode(', ', array_filter([$profile->city, $country])) ?: null,
                'links' => $links,
            ],
            'headline' => $profile->headline,
            'summary' => $primary['profile']['summary'] ?? null,
            'experience' => array_map(fn (array $position): array => [
                ...$position,
                'bullets' => $this->bullets((string) ($position['description'] ?? '')),
            ], array_values(array_filter($pick('positions'), 'is_array'))),
            'education' => $pick('education'),
            'certifications' => $pick('certifications'),
            'projects' => $pick('projects'),
            'skills' => array_slice(array_map(fn (MergedSkill $skill): string => $skill->name, $this->skills->for($user->id)), 0, self::MAX_SKILLS),
            'languages' => $profile->languages ?? [],
        ]);
    }

    /**
     * A position description as bullets: its own lines, or its sentences.
     *
     * @return list<string>
     */
    private function bullets(string $description): array
    {
        $description = trim($description);
        if ($description === '') {
            return [];
        }

        $lines = preg_split('/\R+/u', $description) ?: [];
        if (count($lines) < 2) {
            $lines = preg_split('/(?<=[.!?])\s+(?=\p{Lu})/u', $description) ?: [];
        }

        $bullets = array_map(fn (string $line): string => trim((string) preg_replace('/^[\s•\-*·–]+/u', '', $line)), $lines);

        return array_slice(array_values(array_filter($bullets, fn (string $line): bool => mb_strlen($line) >= 3)), 0, self::MAX_BULLETS);
    }
}
