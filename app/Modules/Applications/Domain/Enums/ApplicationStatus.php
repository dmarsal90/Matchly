<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Enums;

enum ApplicationStatus: string
{
    /** Starred from the listing. */
    case Saved = 'saved';
    /** Cover letter / tailored CV ready (quick apply). */
    case Prepared = 'prepared';
    case Applied = 'applied';
    case Interview = 'interview';
    case Offer = 'offer';
    case Accepted = 'accepted';
    /** The person turned the offer down. */
    case Declined = 'declined';
    /** The employer turned the person down. */
    case Rejected = 'rejected';
    /** The employer never answered. */
    case NoResponse = 'no_response';

    /** Past the point of applying: quick apply and "mark as applied" must not move these back. */
    public function isPastApplying(): bool
    {
        return ! in_array($this, [self::Saved, self::Prepared, self::Applied], true);
    }

    /** Nothing more is expected from the employer. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Accepted, self::Declined, self::Rejected, self::NoResponse], true);
    }
}
