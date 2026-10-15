<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

enum CloseReason: string
{
    case RemovedFromSource = 'removed_from_source';
    case Expired = 'expired';
    case LinkDead = 'link_dead';
    case Moderated = 'moderated';
}
