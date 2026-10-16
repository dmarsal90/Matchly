<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Enums;

enum CoursePrice: string
{
    case Free = 'free';
    case Paid = 'paid';
}
