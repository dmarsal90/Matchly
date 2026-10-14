<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Enums;

/** CEFR levels plus native, comparable when matching job requirements. */
enum LanguageLevel: string
{
    case A1 = 'A1';
    case A2 = 'A2';
    case B1 = 'B1';
    case B2 = 'B2';
    case C1 = 'C1';
    case C2 = 'C2';
    case Native = 'native';

    public function rank(): int
    {
        return match ($this) {
            self::A1 => 1,
            self::A2 => 2,
            self::B1 => 3,
            self::B2 => 4,
            self::C1 => 5,
            self::C2 => 6,
            self::Native => 7,
        };
    }
}
