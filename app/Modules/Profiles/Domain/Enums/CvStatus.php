<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Enums;

enum CvStatus: string
{
    case Uploaded = 'uploaded';
    case Extracted = 'extracted';
    case Failed = 'failed';
}
