<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

/** Broad area used for grouping and filtering listings. */
enum JobCategory: string
{
    case SoftwareDevelopment = 'software_development';
    case DevOps = 'devops';
    case Data = 'data';
    case Qa = 'qa';
    case Security = 'security';
    case Design = 'design';
    case Product = 'product';
    case Marketing = 'marketing';
    case Sales = 'sales';
    case CustomerSupport = 'customer_support';
    case Writing = 'writing';
    case HumanResources = 'human_resources';
    case Finance = 'finance';
    case Legal = 'legal';
    case Operations = 'operations';
    case Education = 'education';
    case Healthcare = 'healthcare';
    case Other = 'other';
}
