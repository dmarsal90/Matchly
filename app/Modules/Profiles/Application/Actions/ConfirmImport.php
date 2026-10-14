<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Domain\Data\ImportSelection;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/**
 * Saves what the user kept from an import: it becomes the source's data
 * (replacing the previously confirmed import) and its skills replace that
 * source's skills. Nothing unselected is kept.
 */
final readonly class ConfirmImport
{
    private const array SECTIONS = ['positions', 'education', 'certifications', 'projects'];

    public function __construct(
        private ConnectionInterface $db,
        private Dispatcher $events,
    ) {}

    public function handle(ProfileImport $import, ImportSelection $selection): void
    {
        if ($import->status !== ImportStatus::PendingReview || $import->payload === null) {
            throw ValidationException::withMessages(['import' => __('skills.import.not_reviewable')]);
        }

        $user = $import->user()->firstOrFail();
        $payload = $this->keepSelected($import->payload, $selection);

        $this->db->transaction(function () use ($import, $user, $payload, $selection): void {
            $user->profileImports()
                ->where('source', $import->source)
                ->whereKeyNot($import->id)
                ->delete();

            $user->skills()->where('source', $import->source)->delete();
            foreach ($payload['skills'] ?? [] as $skill) {
                $user->skills()->create([
                    'source' => $import->source,
                    'skill' => $skill['skill'],
                    'name' => $skill['name'],
                    'level' => $skill['level'] ?? null,
                    'years' => $skill['years'] ?? null,
                    'last_used_year' => $skill['last_used_year'] ?? null,
                    'evidence' => $skill['evidence'] ?? null,
                ]);
            }

            $import->fill(['status' => ImportStatus::Confirmed, 'payload' => $payload, 'confirmed_at' => CarbonImmutable::now()])->save();

            $this->applyToProfile($import, $selection);
        });

        $this->events->dispatch(new ProfileChanged($user->id));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function keepSelected(array $payload, ImportSelection $selection): array
    {
        /** @var list<array<string, mixed>> $skills */
        $skills = $payload['skills'] ?? [];
        $payload['skills'] = array_values(array_filter($skills, fn (array $skill): bool => in_array($skill['skill'], $selection->skills, true)));

        foreach (self::SECTIONS as $section) {
            if (! isset($payload[$section]) || ! is_array($payload[$section])) {
                continue;
            }

            $kept = $selection->items[$section] ?? [];
            $payload[$section] = array_values(array_filter(
                $payload[$section],
                fn (int $index): bool => in_array($index, $kept, true),
                ARRAY_FILTER_USE_KEY,
            ));
        }

        return $payload;
    }

    private function applyToProfile(ProfileImport $import, ImportSelection $selection): void
    {
        $user = $import->user()->firstOrFail();
        $profile = $user->profile()->firstOrNew();

        if ($import->source === SkillSource::Github && $profile->github_username === null) {
            $profile->github_username = $import->reference;
        }

        if ($import->source === SkillSource::Linkedin) {
            if ($selection->headline !== null && trim($selection->headline) !== '') {
                $profile->headline = mb_substr(trim($selection->headline), 0, 120);
            }

            // Imported languages only fill gaps: levels the user set themselves are kept.
            $languages = $profile->languages ?? [];
            $known = array_column($languages, 'code');
            foreach ($selection->languages as $language) {
                if (! in_array($language['code'], $known, true)) {
                    $languages[] = $language;
                    $known[] = $language['code'];
                }
            }
            $profile->languages = $languages;
        }

        $profile->save();
    }
}
