<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\LetterLength;
use App\Modules\Applications\Infrastructure\Pdf\ResumeAtsCheck;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Profiles\Domain\Services\SkillCatalog;

/**
 * Second look at a finished draft, free and without AI: the letter's length
 * and wording, what the job requires that the CV never mentions, and what an
 * ATS would lose from the PDF. Every finding is a warning the person can act
 * on; none blocks the export.
 */
final readonly class DraftReviewer
{
    /** Warning keys this class owns: they are recomputed whenever the draft is edited. */
    public const array KEYS = ['letter_length', 'letter_company', 'letter_placeholder', 'cv_missing_required', 'ats_unreadable', 'ats_missing'];

    /** Tolerance around the target word range before a letter is flagged. */
    private const float SLACK = 0.2;

    public function __construct(
        private SkillCatalog $catalog,
        private ResumeAtsCheck $ats,
    ) {}

    /**
     * @return list<array{key: string, params: array<string, string>}>
     */
    public function review(ResumeData $resume, string $letter, JobListing $job, JobRequirements $requirements, string $language, LetterLength $length): array
    {
        return [
            ...$this->letter($letter, $job, $length),
            ...$this->coverage($resume, $requirements),
            ...$this->ats->check($resume, $language),
        ];
    }

    /**
     * @return list<array{key: string, params: array<string, string>}>
     */
    private function letter(string $letter, JobListing $job, LetterLength $length): array
    {
        $warnings = [];

        if (preg_match('/\[[^\]\n]{2,40}\]|\{\{[^}]*\}\}|lorem ipsum|<[a-z ]+>/iu', $letter) === 1) {
            $warnings[] = ['key' => 'letter_placeholder', 'params' => []];
        }

        [$min, $max] = array_map(intval(...), explode('-', $length->words()));
        $words = preg_match_all('/[\p{L}\p{N}\'’-]+/u', $letter);
        if ($words < (int) floor($min * (1 - self::SLACK)) || $words > (int) ceil($max * (1 + self::SLACK))) {
            $warnings[] = ['key' => 'letter_length', 'params' => ['words' => (string) $words, 'target' => $length->words()]];
        }

        if (mb_stripos($letter, $job->company) === false) {
            $warnings[] = ['key' => 'letter_company', 'params' => ['company' => $job->company]];
        }

        return $warnings;
    }

    /**
     * Required skills the CV never mentions: if the person has them, saying so is the cheapest way to raise the match.
     *
     * @return list<array{key: string, params: array<string, string>}>
     */
    private function coverage(ResumeData $resume, JobRequirements $requirements): array
    {
        if ($requirements->requiredSkills === []) {
            return [];
        }

        $present = array_keys($this->catalog->detectIn($resume->plainText()));
        foreach ($resume->skills as $skill) {
            $present[] = $this->catalog->normalize($skill)->slug ?? '';
        }

        $absent = array_values(array_filter($requirements->requiredSkills, fn (array $skill): bool => ! in_array($skill['skill'], $present, true)));

        return $absent === [] ? [] : [['key' => 'cv_missing_required', 'params' => ['skills' => implode(', ', array_column(array_slice($absent, 0, 5), 'name'))]]];
    }
}
