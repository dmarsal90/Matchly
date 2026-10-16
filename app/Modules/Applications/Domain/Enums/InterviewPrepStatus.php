<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Enums;

enum InterviewPrepStatus: string
{
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';
}
