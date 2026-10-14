<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Enums;

/**
 * Non-negotiable conditions: a job that breaks one is not a match, whatever
 * its score. Only conditions the job data can actually answer are offered.
 */
enum Dealbreaker: string
{
    case RemoteOnly = 'remote_only';
    case SalaryDisclosed = 'salary_disclosed';
    case SalaryFloor = 'salary_floor';
    case EmploymentTypeStrict = 'employment_type_strict';
    case RegionStrict = 'region_strict';
}
