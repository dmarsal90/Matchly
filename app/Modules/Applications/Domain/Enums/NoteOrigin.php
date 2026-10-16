<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Enums;

enum NoteOrigin: string
{
    /** Written by the person. */
    case Manual = 'manual';
    /** Found on the company's public pages; used only once approved. */
    case Web = 'web';
}
