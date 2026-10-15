<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Enums;

enum ApplicationEventType: string
{
    case StatusChanged = 'status_changed';
    /** One round of interviews; numbered per application. */
    case Interview = 'interview';
    case Note = 'note';
    /** The person followed up with the employer. */
    case FollowUp = 'follow_up';
}
