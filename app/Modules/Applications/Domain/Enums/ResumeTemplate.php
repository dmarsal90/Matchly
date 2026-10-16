<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Enums;

/**
 * Looks of the exported CV. All of them stay one column of real text with the
 * same conventional headings, so an applicant tracking system reads each one
 * the same; they differ only in type size, spacing and a touch of colour.
 */
enum ResumeTemplate: string
{
    /** Black on white, neutral rules: the safe default. */
    case Classic = 'classic';
    /** A dark blue accent on the headings, a little more air. */
    case Modern = 'modern';
    /** Smaller type and tight spacing, to fit a long career on fewer pages. */
    case Compact = 'compact';
}
