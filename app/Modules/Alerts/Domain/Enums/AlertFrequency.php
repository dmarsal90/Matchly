<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Domain\Enums;

enum AlertFrequency: string
{
    /** As soon as matching jobs arrive. */
    case Immediate = 'immediate';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function isDigest(): bool
    {
        return $this !== self::Immediate;
    }
}
