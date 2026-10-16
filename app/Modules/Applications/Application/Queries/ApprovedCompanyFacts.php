<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Queries;

use App\Modules\Applications\Domain\Services\CompanyKey;
use App\Modules\Applications\Infrastructure\Models\CompanyNote;

/**
 * What the person has approved about a company, oldest first. The letter and
 * the interview pack may mention these facts and nothing else about it.
 */
final class ApprovedCompanyFacts
{
    /** Enough to show interest without turning the letter into a dossier. */
    private const int MAX = 5;

    /**
     * @return list<string>
     */
    public function for(int $userId, string $company): array
    {
        $key = CompanyKey::from($company);
        if ($key === '') {
            return [];
        }

        $facts = CompanyNote::query()
            ->where('user_id', $userId)
            ->where('company_key', $key)
            ->where('approved', true)
            ->orderBy('id')
            ->limit(self::MAX)
            ->get()
            ->map(fn (CompanyNote $note): string => rtrim(trim($note->fact), " .\t\n"))
            ->all();

        return array_values(array_filter($facts, fn (string $fact): bool => $fact !== ''));
    }
}
