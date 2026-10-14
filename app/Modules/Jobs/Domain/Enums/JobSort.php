<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

enum JobSort: string
{
    case Newest = 'newest';
    /** Full-text rank; only meaningful with a search query. */
    case Relevance = 'relevance';
    case Salary = 'salary';
}
