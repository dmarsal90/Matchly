<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Contracts;

use App\Modules\Applications\Infrastructure\Models\ApplicationPackage;

/**
 * Extension point for a future browser extension that fills application
 * forms on the employer's site with an approved package. Nothing is ever
 * submitted automatically: the extension only pre-fills, the person sends.
 */
interface ApplicationAssistant
{
    /**
     * Values for common application form fields, keyed by a stable field
     * name (first_name, email, linkedin, cover_letter…).
     *
     * @return array<string, string>
     */
    public function fieldsFor(ApplicationPackage $package): array;
}
