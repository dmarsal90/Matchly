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
    case Rejected = 'rejected';
}
