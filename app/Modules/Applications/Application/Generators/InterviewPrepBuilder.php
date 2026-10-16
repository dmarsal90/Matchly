<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Applications\Application\Queries\ApprovedCompanyFacts;
use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\ContentSource;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\JobSnapshotFactory;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use Illuminate\Support\Str;
use Locale;

/**
 * Builds the interview pack from the person's own CV and the posting.
 * Without AI it is assembled from templates: STAR outlines quote the CV's
 * own bullets, and skills the CV lacks get an honest "not yet" answer. With
 * AI the wording is richer, but anything that names a skill the CV does not
 * back is dropped, and gap answers are limited to the real gaps.
 *
 * @phpstan-type Star array{situation: string|null, task: string|null, action: string|null, result: string|null}
 * @phpstan-type Question array{category: string, question: string, skill: string|null, star: Star|null}
 * @phpstan-type Gap array{skill: string, required: bool, answer: string}
 * @phpstan-type Pack array{questions: list<Question>, gaps: list<Gap>, ask: list<string>, source: ContentSource, aiError: string|null, warnings: list<array{key: string, params: array<string, string>}>}
 */
final readonly class InterviewPrepBuilder
{
    private const int MAX_SKILLS = 6;

    public function __construct(private AiGateway $ai, private SkillCatalog $catalog, private ApprovedCompanyFacts $companyFacts) {}

    /**
     * @return Pack
     */
    public function build(User $user, ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language, bool $useAi): array
    {
        $locale = in_array($language, CoverLetterWriter::TEMPLATE_LANGUAGES, true) ? $language : 'en';
        $skills = $this->jobSkills($requirements);
        $own = $this->ownSlugs($resume);
        $gapSkills = array_values(array_filter($skills, fn (array $skill): bool => ! in_array($skill['slug'], $own, true)));

        $facts = $this->companyFacts->for($user->id, $job->company);
        $rules = $this->fromRules($resume, $job, $skills, $own, $gapSkills, $locale, $facts);
        $warnings = $resume->isEmpty() ? [['key' => 'empty_profile', 'params' => []]] : [];

        if (! $useAi) {
            return [...$rules, 'source' => ContentSource::Rules, 'aiError' => null, 'warnings' => $warnings];
        }

        try {
            $response = $this->ai->run(AiTask::InterviewPrep, $this->input($resume, $job, $requirements, $language, $gapSkills, $facts), $user);
        } catch (AiUnavailable $exception) {
            return [...$rules, 'source' => ContentSource::Rules, 'aiError' => $exception->reason, 'warnings' => [...$warnings, ['key' => 'ai_unavailable', 'params' => ['reason' => $exception->reason]]]];
        }

        $verified = $this->verified($response->data, $resume, $own, $gapSkills, $rules);
        if ($verified['removed'] > 0) {
            $warnings[] = ['key' => 'star_removed', 'params' => ['count' => (string) $verified['removed']]];
        }

        return [
            'questions' => $verified['questions'] !== [] ? $verified['questions'] : $rules['questions'],
            'gaps' => $verified['gaps'],
            'ask' => $verified['ask'] !== [] ? $verified['ask'] : $rules['ask'],
            'source' => $verified['questions'] !== [] ? ContentSource::Ai : ContentSource::Rules,
            'aiError' => null,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return list<array{slug: string, name: string, required: bool}>
     */
    private function jobSkills(JobRequirements $requirements): array
    {
        $skills = [];
        foreach ($requirements->requiredSkills as $skill) {
            $skills[$skill['skill']] ??= ['slug' => $skill['skill'], 'name' => $skill['name'], 'required' => true];
        }
        foreach ($requirements->niceToHaveSkills as $skill) {
            $skills[$skill['skill']] ??= ['slug' => $skill['skill'], 'name' => $skill['name'], 'required' => false];
        }

        return array_slice(array_values($skills), 0, self::MAX_SKILLS);
    }

    /**
     * @return list<string>
     */
    private function ownSlugs(ResumeData $resume): array
    {
        $own = array_keys($this->catalog->detectIn($resume->plainText()));
        foreach ($resume->skills as $skill) {
            $own[] = $this->catalog->normalize($skill)->slug ?? '';
        }

        return array_values(array_unique(array_filter($own)));
    }

    /**
     * @param  list<array{slug: string, name: string, required: bool}>  $skills
     * @param  list<string>  $own
     * @param  list<array{slug: string, name: string, required: bool}>  $gapSkills
     * @param  list<string>  $facts
     * @return array{questions: list<Question>, gaps: list<Gap>, ask: list<string>}
     */
    private function fromRules(ResumeData $resume, JobListing $job, array $skills, array $own, array $gapSkills, string $locale, array $facts): array
    {
        $t = fn (string $key, array $params = []): string => (string) __("interview_prep.template.{$key}", $params, $locale);

        $questions = [];
        foreach ($skills as $skill) {
            if (in_array($skill['slug'], $own, true)) {
                $questions[] = ['category' => 'technical', 'question' => $t('technical', ['skill' => $skill['name']]), 'skill' => $skill['name'], 'star' => $this->outline($resume, $skill['slug'], $t)];
            }
        }
        foreach (['conflict', 'failure', 'deadline'] as $topic) {
            $questions[] = ['category' => 'behavioral', 'question' => $t("behavioral.{$topic}"), 'skill' => null, 'star' => null];
        }
        $questions[] = ['category' => 'motivation', 'question' => $t('motivation.company', ['company' => $job->company]), 'skill' => null, 'star' => null];
        $questions[] = ['category' => 'motivation', 'question' => $t('motivation.role', ['title' => $job->title]), 'skill' => null, 'star' => null];

        $closest = array_map(
            fn (array $skill): string => $skill['name'],
            array_slice(array_values(array_filter($skills, fn (array $skill): bool => in_array($skill['slug'], $own, true))), 0, 2),
        ) ?: array_slice($resume->skills, 0, 2);
        $and = $t('and');
        $gaps = array_map(fn (array $skill): array => [
            'skill' => $skill['name'],
            'required' => $skill['required'],
            'answer' => $closest === []
                ? $t('gap.none', ['skill' => $skill['name']])
                : $t('gap.close', ['skill' => $skill['name'], 'closest' => implode(" {$and} ", $closest)]),
        ], $gapSkills);

        return [
            'questions' => $questions,
            'gaps' => $gaps,
            'ask' => [
                ...array_map(fn (string $fact): string => $t('ask.fact', ['fact' => $fact]), array_slice($facts, 0, 2)),
                $t('ask.team'),
                $t('ask.success', ['title' => $job->title]),
                $t('ask.process', ['company' => $job->company]),
            ],
        ];
    }

    /**
     * A STAR outline quoting the CV: where it happened and what the person
     * wrote they did. Task and result stay empty for the person to fill in.
     *
     * @return Star
     */
    private function outline(ResumeData $resume, string $slug, callable $t): array
    {
        foreach ($resume->experience as $position) {
            foreach ($position['bullets'] as $bullet) {
                if (array_key_exists($slug, $this->catalog->detectIn($bullet))) {
                    return ['situation' => $this->situation($position, $t), 'task' => null, 'action' => $bullet, 'result' => null];
                }
            }
        }
        foreach ($resume->experience as $position) {
            if (array_key_exists($slug, $this->catalog->detectIn(implode(' ', [$position['title'], ...$position['bullets']])))) {
                return ['situation' => $this->situation($position, $t), 'task' => null, 'action' => null, 'result' => null];
            }
        }

        return ['situation' => null, 'task' => null, 'action' => null, 'result' => null];
    }

    /**
     * @param  array{title: string, company: string|null}  $position
     */
    private function situation(array $position, callable $t): string
    {
        return $position['company'] !== null
            ? $t('situation_at', ['title' => $position['title'], 'company' => $position['company']])
            : $position['title'];
    }

    /**
     * @param  list<array{slug: string, name: string, required: bool}>  $gapSkills
     * @param  list<string>  $facts
     */
    private function input(ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language, array $gapSkills, array $facts): string
    {
        return (string) json_encode([
            'language' => Locale::getDisplayLanguage($language, 'en') ?: $language,
            'candidate' => [
                'headline' => $resume->headline,
                'summary' => $resume->summary,
                'skills' => $resume->skills,
                'experience' => array_map(fn (array $position): array => [
                    'title' => $position['title'],
                    'company' => $position['company'],
                    'from' => $position['started_on'],
                    'to' => $position['finished_on'] ?? 'present',
                    'highlights' => array_slice($position['bullets'], 0, 5),
                ], array_slice($resume->experience, 0, 5)),
            ],
            'job' => [
                'title' => $job->title,
                'company' => $job->company,
                'company_facts' => $facts,
                'requirements' => [
                    'required_skills' => array_column($requirements->requiredSkills, 'name'),
                    'nice_to_have_skills' => array_column($requirements->niceToHaveSkills, 'name'),
                ],
                'description' => Str::limit(JobSnapshotFactory::plainText($job->description), 3000),
            ],
            'gap_skills' => array_column($gapSkills, 'name'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Keeps only what the CV backs: an outline that names a skill the CV
     * lacks loses its STAR text, and gap answers are tied to the real gaps.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $own
     * @param  list<array{slug: string, name: string, required: bool}>  $gapSkills
     * @param  array{questions: list<Question>, gaps: list<Gap>, ask: list<string>}  $rules
     * @return array{questions: list<Question>, gaps: list<Gap>, ask: list<string>, removed: int}
     */
    private function verified(array $data, ResumeData $resume, array $own, array $gapSkills, array $rules): array
    {
        $removed = 0;
        $questions = [];
        foreach ((array) ($data['questions'] ?? []) as $item) {
            if (! is_array($item) || ! is_string($item['question'] ?? null)) {
                continue;
            }
            $star = array_map(fn (string $key): ?string => ($text = trim((string) ($item[$key] ?? ''))) !== '' ? $text : null, array_combine(['situation', 'task', 'action', 'result'], ['situation', 'task', 'action', 'result']));
            $hasStar = array_filter($star) !== [];
            if ($hasStar && $this->unbacked(implode(' ', array_filter($star)), $own, []) !== []) {
                $star = ['situation' => null, 'task' => null, 'action' => null, 'result' => null];
                $hasStar = false;
                $removed++;
            }
            $skill = trim((string) ($item['skill'] ?? ''));
            $questions[] = [
                'category' => in_array($item['category'] ?? null, ['technical', 'behavioral', 'motivation'], true) ? (string) $item['category'] : 'behavioral',
                'question' => trim($item['question']),
                'skill' => $skill !== '' ? $skill : null,
                'star' => $hasStar || $skill !== '' ? $star : null,
            ];
        }

        $answers = [];
        foreach ((array) ($data['gaps'] ?? []) as $item) {
            if (is_array($item) && is_string($item['skill'] ?? null) && is_string($item['answer'] ?? null)) {
                $answers[$this->catalog->normalize($item['skill'])->slug ?? Str::slug($item['skill'])] = trim($item['answer']);
            }
        }
        $allowed = array_column($gapSkills, 'slug');
        $ruleAnswers = array_column($rules['gaps'], 'answer', 'skill');
        $gaps = array_map(function (array $skill) use ($answers, $own, $allowed, $ruleAnswers): array {
            $answer = $answers[$skill['slug']] ?? null;
            // A gap answer may name the gap skill itself, but not other skills the CV lacks.
            $usable = $answer !== null && $this->unbacked($answer, $own, $allowed) === [];

            return ['skill' => $skill['name'], 'required' => $skill['required'], 'answer' => $usable ? $answer : $ruleAnswers[$skill['name']]];
        }, $gapSkills);

        $ask = array_values(array_filter(array_map(fn (mixed $item): string => is_string($item) ? trim($item) : '', (array) ($data['ask'] ?? []))));

        return ['questions' => $questions, 'gaps' => $gaps, 'ask' => $ask, 'removed' => $removed];
    }

    /**
     * Known skills named in a text that the CV does not back.
     *
     * @param  list<string>  $own
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function unbacked(string $text, array $own, array $allowed): array
    {
        return array_values(array_diff(array_keys($this->catalog->detectIn($text)), $own, $allowed));
    }
}
