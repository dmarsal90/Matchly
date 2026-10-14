<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

enum JobGroup: string
{
    case Category = 'category';
    case Role = 'role';
    case Source = 'source';

    /** Column of job_listings the groups are built from. */
    public function column(): string
    {
        return match ($this) {
            self::Category => 'category',
            self::Role => 'role',
            self::Source => 'source_id',
        };
    }
}
