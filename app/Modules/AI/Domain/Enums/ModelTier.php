<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\Enums;

enum ModelTier: string
{
    /** Cheap and quick: extraction and summaries. */
    case Fast = 'fast';

    /** Better writing: cover letters and CV tailoring. */
    case Quality = 'quality';
}
