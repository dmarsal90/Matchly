<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

enum JobStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    /** Held back from listings until an admin reviews it. */
    case Flagged = 'flagged';
}
