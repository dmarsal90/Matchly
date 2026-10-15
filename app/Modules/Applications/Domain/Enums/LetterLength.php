<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Enums;

enum LetterLength: string
{
    case Short = 'short';
    case Medium = 'medium';
    case Long = 'long';

    /** Target word count range given to the writer. */
    public function words(): string
    {
        return match ($this) {
            self::Short => '120-180',
            self::Medium => '200-280',
            self::Long => '300-400',
        };
    }
}
