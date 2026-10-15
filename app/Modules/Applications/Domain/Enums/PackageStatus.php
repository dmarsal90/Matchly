<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Enums;

enum PackageStatus: string
{
    case Generating = 'generating';
    /** Ready for the user to review and edit. */
    case Draft = 'draft';
    /** Reviewed by the user: it can be exported. */
    case Approved = 'approved';
    case Failed = 'failed';
}
