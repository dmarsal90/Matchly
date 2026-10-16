<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\Enums;

/**
 * Every AI use case, with its model tier and output budget. The value names
 * the versioned prompt and JSON schema files (Prompts/<version>/<value>.*).
 */
enum AiTask: string
{
    case ExtractJobRequirements = 'extract_job_requirements';
    case StructureCv = 'structure_cv';
    case SummarizeGithub = 'summarize_github';
    case TailorCv = 'tailor_cv';
    case CoverLetter = 'cover_letter';
    case InterviewPrep = 'interview_prep';

    public function tier(): ModelTier
    {
        return match ($this) {
            self::TailorCv, self::CoverLetter, self::InterviewPrep => ModelTier::Quality,
            default => ModelTier::Fast,
        };
    }

    public function maxOutputTokens(): int
    {
        return match ($this) {
            self::ExtractJobRequirements => 1500,
            self::StructureCv => 4000,
            self::SummarizeGithub => 1200,
            self::TailorCv => 3000,
            self::CoverLetter => 1500,
            self::InterviewPrep => 3500,
        };
    }

    /** Input is cut to this many characters (long postings and CVs). */
    public function maxInputChars(): int
    {
        return match ($this) {
            self::ExtractJobRequirements => 12000,
            self::StructureCv => 20000,
            self::SummarizeGithub => 15000,
            self::TailorCv, self::CoverLetter, self::InterviewPrep => 20000,
        };
    }

    /** Run for the platform, not for a user: paid from the system budget. */
    public function isSystem(): bool
    {
        return $this === self::ExtractJobRequirements;
    }
}
