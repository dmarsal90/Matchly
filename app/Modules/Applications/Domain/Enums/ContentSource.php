<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Enums;

/** Who produced a piece of a package. */
enum ContentSource: string
{
    case Ai = 'ai';
    /** Deterministic: templates and rules, no AI involved. */
    case Rules = 'rules';
    /** Edited by the user. */
    case User = 'user';
}
