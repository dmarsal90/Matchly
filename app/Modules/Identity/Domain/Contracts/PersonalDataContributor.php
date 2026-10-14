<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

use App\Modules\Identity\Domain\Data\PersonalData;

/**
 * Every module that stores personal data registers one of these, so data
 * export (GDPR art. 15/20) and erasure (art. 17) always cover everything.
 */
interface PersonalDataContributor
{
    public function export(int $userId): PersonalData;

    /** Deletes data the database cascade cannot reach (files, external copies). */
    public function erase(int $userId): void;
}
