<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Enums;

enum ImportStatus: string
{
    case Processing = 'processing';
    case PendingReview = 'pending_review';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
}
