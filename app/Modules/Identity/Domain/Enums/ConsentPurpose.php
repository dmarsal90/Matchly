<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enums;

/**
 * Each optional processing of personal data needs its own explicit,
 * revocable consent (GDPR art. 6.1.a / 7). Revoking stops the processing
 * and deletes the data obtained through it.
 */
enum ConsentPurpose: string
{
    case CvProcessing = 'cv_processing';
    case GithubImport = 'github_import';
    case LinkedinImport = 'linkedin_import';
    case PortfolioImport = 'portfolio_import';
    case AiProcessing = 'ai_processing';
    case EmailAlerts = 'email_alerts';
}
