<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Enums;

/** Specific role inferred from the title; finer-grained than JobCategory. */
enum JobRole: string
{
    case Frontend = 'frontend';
    case Backend = 'backend';
    case Fullstack = 'fullstack';
    case Mobile = 'mobile';
    case DevOps = 'devops';
    case DataEngineer = 'data_engineer';
    case DataScientist = 'data_scientist';
    case DataAnalyst = 'data_analyst';
    case MachineLearning = 'machine_learning';
    case Qa = 'qa';
    case Security = 'security';
    case Designer = 'designer';
    case ProductManager = 'product_manager';
    case ProjectManager = 'project_manager';
    case EngineeringManager = 'engineering_manager';
    case Marketing = 'marketing';
    case Sales = 'sales';
    case CustomerSupport = 'customer_support';
    case ContentWriter = 'content_writer';
    case Recruiter = 'recruiter';
    case Finance = 'finance';
    case Operations = 'operations';

    public function category(): JobCategory
    {
        return match ($this) {
            self::Frontend, self::Backend, self::Fullstack, self::Mobile, self::EngineeringManager => JobCategory::SoftwareDevelopment,
            self::DevOps => JobCategory::DevOps,
            self::DataEngineer, self::DataScientist, self::DataAnalyst, self::MachineLearning => JobCategory::Data,
            self::Qa => JobCategory::Qa,
            self::Security => JobCategory::Security,
            self::Designer => JobCategory::Design,
            self::ProductManager, self::ProjectManager => JobCategory::Product,
            self::Marketing => JobCategory::Marketing,
            self::Sales => JobCategory::Sales,
            self::CustomerSupport => JobCategory::CustomerSupport,
            self::ContentWriter => JobCategory::Writing,
            self::Recruiter => JobCategory::HumanResources,
            self::Finance => JobCategory::Finance,
            self::Operations => JobCategory::Operations,
        };
    }
}
