<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

enum WorkMode: string
{
    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Onsite = 'onsite';
}
