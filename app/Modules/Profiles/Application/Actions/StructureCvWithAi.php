<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Profiles\Application\Support\SourceText;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Profiles\Infrastructure\Imports\PositionSkills;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;

/**
 * Turns the primary CV into structured experience, education and skills
 * with the AI, for the user to review. Everything the AI returns is checked
 * against the CV text and dropped if it is not there; if the AI is not
 * available the keyword detection is used instead.
 */
final readonly class StructureCvWithAi
{
    private const float MIN_SUMMARY_OVERLAP = 0.8;

    public function __construct(
        private AiGateway $ai,
        private SkillCatalog $catalog,
    ) {}

    public function handle(ProfileImport $import): void
    {
        $user = $import->user;
        $cv = $user !== null ? CvDocument::query()->where('user_id', $user->id)->find((int) $import->reference) : null;

        if ($user === null || $cv === null || $cv->extracted_text === null || ! $user->hasConsented(ConsentPurpose::AiProcessing)) {
            $import->delete();

            return;
        }

        $text = $cv->extracted_text;
        $mentions = $this->catalog->detectIn($text);
        $payload = ['cv' => ['id' => $cv->id, 'name' => $cv->original_name]];

        try {
            $response = $this->ai->run(AiTask::StructureCv, $text, $user);
            $payload += $this->verified($response->data, new SourceText($text), $mentions);
            $payload['ai'] = ['provider' => $response->provider, 'model' => $response->model];
        } catch (AiUnavailable $exception) {
            $payload['skills'] = (new PositionSkills($this->catalog))->combine($this->listed($mentions, []), [], ['mentions' => 0]);
            $payload['ai_error'] = $exception->reason;
        }

        $import->fill(['status' => ImportStatus::PendingReview, 'payload' => $payload, 'error' => null])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $mentions
     * @return array<string, mixed>
     */
    private function verified(array $data, SourceText $source, array $mentions): array
    {
        $date = fn (mixed $value): ?string => is_string($value) && $source->containsYearOf($value) ? $value : null;
        $nullable = fn (mixed $value): ?string => is_string($value) && $source->contains($value) ? $value : null;

        $positions = [];
        foreach ((array) ($data['positions'] ?? []) as $position) {
            if (is_array($position) && $source->contains($position['title'] ?? null)) {
                $positions[] = [
                    'title' => (string) $position['title'],
                    'company' => $nullable($position['company'] ?? null),
                    'description' => $this->faithful($position['description'] ?? null, $source),
                    'location' => null,
                    'started_on' => $date($position['started_on'] ?? null),
                    'finished_on' => $date($position['finished_on'] ?? null),
                ];
            }
        }

        $education = [];
        foreach ((array) ($data['education'] ?? []) as $item) {
            if (is_array($item) && $source->contains($item['school'] ?? null)) {
                $education[] = [
                    'school' => (string) $item['school'],
                    'degree' => $nullable($item['degree'] ?? null),
                    'notes' => null,
                    'started_on' => $date($item['started_on'] ?? null),
                    'finished_on' => $date($item['finished_on'] ?? null),
                ];
            }
        }

        $certifications = [];
        foreach ((array) ($data['certifications'] ?? []) as $item) {
            if (is_array($item) && $source->contains($item['name'] ?? null)) {
                $certifications[] = ['name' => (string) $item['name'], 'authority' => $nullable($item['authority'] ?? null), 'url' => null, 'started_on' => null, 'finished_on' => null];
            }
        }

        $projects = [];
        foreach ((array) ($data['projects'] ?? []) as $item) {
            if (is_array($item) && $source->contains($item['title'] ?? null)) {
                $projects[] = ['title' => (string) $item['title'], 'description' => $this->faithful($item['description'] ?? null, $source), 'url' => null, 'started_on' => null, 'finished_on' => null];
            }
        }

        $aiSkills = array_values(array_filter((array) ($data['skills'] ?? []), fn (mixed $skill): bool => is_string($skill) && $source->contains($skill)));
        $headline = $nullable($data['headline'] ?? null);

        return [
            'profile' => $headline !== null ? ['headline' => $headline, 'summary' => null, 'industry' => null, 'location' => null, 'websites' => []] : null,
            'positions' => $positions,
            'education' => $education,
            'certifications' => $certifications,
            'projects' => $projects,
            'skills' => (new PositionSkills($this->catalog))->combine($this->listed($mentions, $aiSkills), $positions, ['mentions' => 0]),
        ];
    }

    /** Summaries are kept only when they stick to the CV's own words. */
    private function faithful(mixed $summary, SourceText $source): ?string
    {
        return is_string($summary) && $source->overlap($summary) >= self::MIN_SUMMARY_OVERLAP ? $summary : null;
    }

    /**
     * @param  array<string, int>  $mentions  catalog skills found in the text
     * @param  list<string>  $aiSkills  verified skill names from the AI
     * @return array<string, array{name: string, evidence: array<string, mixed>}>
     */
    private function listed(array $mentions, array $aiSkills): array
    {
        $listed = [];
        foreach ($mentions as $slug => $count) {
            $listed[$slug] = ['name' => $this->catalog->label($slug, $slug), 'evidence' => ['mentions' => $count]];
        }
        foreach ($aiSkills as $name) {
            $skill = $this->catalog->normalize($name);
            if ($skill !== null) {
                $listed[$skill->slug] ??= ['name' => $skill->label, 'evidence' => ['mentions' => 1]];
            }
        }

        return $listed;
    }
}
