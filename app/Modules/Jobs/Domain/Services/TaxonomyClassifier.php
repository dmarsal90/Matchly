<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Domain\Services;

use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\Seniority;

/**
 * Deterministic role, category and seniority inference from the title and the
 * source's own category label. Order matters: the first matching rule wins,
 * so more specific patterns come first.
 */
final class TaxonomyClassifier
{
    /** @var array<string, JobRole> */
    private const array ROLE_PATTERNS = [
        '/\bengineering manager|head of engineering|vp of engineering|director of engineering\b/' => JobRole::EngineeringManager,
        '/\b(machine learning|ml|ai|deep learning|llm|nlp|computer vision)\b.*\b(engineer|scientist|researcher|developer)\b|\bmlops\b/' => JobRole::MachineLearning,
        '/\bdata scien/' => JobRole::DataScientist,
        '/\bdata engineer|analytics engineer|etl\b|big data/' => JobRole::DataEngineer,
        '/\b(data|business intelligence|bi|business|product|marketing) analyst\b|\bdata analytics\b/' => JobRole::DataAnalyst,
        '/\b(devops|sre|site reliability|platform engineer|infrastructure engineer|cloud engineer|systems? administrator|sysadmin|kubernetes)\b/' => JobRole::DevOps,
        '/\b(security|appsec|infosec|cyber|penetration|pentest|soc analyst)\b/' => JobRole::Security,
        '/\b(qa|quality assurance|test automation|sdet|tester|testing engineer)\b/' => JobRole::Qa,
        '/\b(ios|android|mobile|react native|flutter|swift|kotlin)\b.*\b(developer|engineer)\b/' => JobRole::Mobile,
        '/\b(full[- ]?stack)\b/' => JobRole::Fullstack,
        '/\b(front[- ]?end|ui engineer|ui developer|react developer|vue developer|angular developer|web developer)\b/' => JobRole::Frontend,
        '/\b(back[- ]?end|api engineer|server[- ]side|php|laravel|django|rails|golang|java|node\.?js|python|ruby|\.net|c#|scala|elixir|rust)\b.*\b(developer|engineer)\b|\bback[- ]?end\b/' => JobRole::Backend,
        '/\b(ux|ui|product designer|graphic designer|visual designer|web designer|brand designer|motion designer|designer|illustrator)\b/' => JobRole::Designer,
        '/\b(product manager|product owner|head of product|group product manager)\b/' => JobRole::ProductManager,
        '/\b(project manager|program manager|scrum master|delivery manager|agile coach)\b/' => JobRole::ProjectManager,
        '/\b(recruit|talent acquisition|sourcer|people partner|hr\b|human resources)/' => JobRole::Recruiter,
        '/\b(customer support|customer success|customer service|support specialist|support engineer|technical support|helpdesk|help desk|service desk|customer care|customer experience)\b/' => JobRole::CustomerSupport,
        '/\b(account executive|account director|enterprise account|sales|business development|bdr|sdr|account manager|partnerships)\b/' => JobRole::Sales,
        '/\b(marketing|seo|growth|social media|community manager|ppc|demand generation|brand manager)\b/' => JobRole::Marketing,
        '/\b(copywriter|content writer|technical writer|writer|editor|content strategist|content manager)\b/' => JobRole::ContentWriter,
        '/\b(accountant|accounting|finance|financial|controller|bookkeep|payroll|tax)\b/' => JobRole::Finance,
        '/\b(operations|ops manager|office manager|assistant|administrative)\b/' => JobRole::Operations,
        // A bare "engineer" may be civil, CFD, sales... only software titles fall back to backend.
        '/\b(software engineer|software developer|developer|programmer)\b/' => JobRole::Backend,
    ];

    /** @var array<string, JobCategory> */
    private const array CATEGORY_HINTS = [
        '/software|develop|programming|engineering|frontend|backend|full[- ]?stack|\bit\b|tech/' => JobCategory::SoftwareDevelopment,
        '/devops|sysadmin|infrastructure/' => JobCategory::DevOps,
        '/data|analytics|machine learning|\bai\b/' => JobCategory::Data,
        '/\bqa\b|quality/' => JobCategory::Qa,
        '/security/' => JobCategory::Security,
        '/design|ux|ui/' => JobCategory::Design,
        '/product/' => JobCategory::Product,
        '/marketing|seo|social media/' => JobCategory::Marketing,
        '/sales|business development/' => JobCategory::Sales,
        '/customer|support|service/' => JobCategory::CustomerSupport,
        '/writ|content|copy|editor/' => JobCategory::Writing,
        '/\bhr\b|human resources|recruit|people|talent/' => JobCategory::HumanResources,
        '/financ|accounting/' => JobCategory::Finance,
        '/legal|law/' => JobCategory::Legal,
        '/teach|education|tutor/' => JobCategory::Education,
        '/health|medical|nurs|clinic/' => JobCategory::Healthcare,
        '/operations|admin|assistant|project/' => JobCategory::Operations,
    ];

    public function role(string $title): ?JobRole
    {
        $title = strtolower($title);

        foreach (self::ROLE_PATTERNS as $pattern => $role) {
            if (preg_match($pattern, $title) === 1) {
                return $role;
            }
        }

        return null;
    }

    public function category(?JobRole $role, ?string $categoryHint): ?JobCategory
    {
        if ($role !== null) {
            return $role->category();
        }

        if ($categoryHint === null || trim($categoryHint) === '') {
            return null;
        }

        $hint = strtolower($categoryHint);

        foreach (self::CATEGORY_HINTS as $pattern => $category) {
            if (preg_match($pattern, $hint) === 1) {
                return $category;
            }
        }

        return JobCategory::Other;
    }

    public function seniority(string $title): ?Seniority
    {
        return Seniority::fromLabel($title);
    }
}
