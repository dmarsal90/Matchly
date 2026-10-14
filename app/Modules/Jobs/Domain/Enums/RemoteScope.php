<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

/**
 * Where a remote/hybrid job can be done from. Null on a job means the source
 * did not say, which is different from "worldwide".
 */
enum RemoteScope: string
{
    case Worldwide = 'worldwide';
    case Region = 'region';
    case CountryRestricted = 'country_restricted';
}
