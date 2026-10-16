<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Enums;

use App\Modules\Identity\Domain\Enums\ConsentPurpose;

/**
 * Where a skill claim comes from. Everything but "declared" is evidence:
 * something the user did or wrote somewhere else.
 */
enum SkillSource: string
{
    case Declared = 'declared';
    case Cv = 'cv';
    case Github = 'github';
    case Linkedin = 'linkedin';
    case Portfolio = 'portfolio';

    public function isEvidence(): bool
    {
        return $this !== self::Declared;
    }

    public function consentPurpose(): ?ConsentPurpose
    {
        return match ($this) {
            self::Declared => null,
            self::Cv => ConsentPurpose::CvProcessing,
            self::Github => ConsentPurpose::GithubImport,
            self::Linkedin => ConsentPurpose::LinkedinImport,
            self::Portfolio => ConsentPurpose::PortfolioImport,
        };
    }

    public static function forConsent(ConsentPurpose $purpose): ?self
    {
        foreach (self::cases() as $source) {
            if ($source->consentPurpose() === $purpose) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Sources whose data arrives through an import the user reviews.
     *
     * @return list<self>
     */
    public static function imported(): array
    {
        return array_values(array_filter(self::cases(), fn (self $source): bool => $source->isEvidence()));
    }
}
