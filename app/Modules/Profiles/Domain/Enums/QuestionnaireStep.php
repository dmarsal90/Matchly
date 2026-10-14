<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Enums;

/** Onboarding questionnaire steps, in order. Each one is saved on its own. */
enum QuestionnaireStep: string
{
    case Role = 'role';
    case Skills = 'skills';
    case Languages = 'languages';
    case Location = 'location';
    case Conditions = 'conditions';

    public function next(): ?self
    {
        $cases = self::cases();
        $index = array_search($this, $cases, true);

        return $cases[(int) $index + 1] ?? null;
    }

    public function isLast(): bool
    {
        return $this->next() === null;
    }
}
