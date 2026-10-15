<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Enums;

enum LetterTone: string
{
    case Formal = 'formal';
    case Friendly = 'friendly';
    case Enthusiastic = 'enthusiastic';
}
