<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Http\Requests;

use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Jobs\Application\Queries\JobFiltersParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAlertRequest extends FormRequest
{
    public const int MAX_ALERTS = 20;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'filters' => ['present', 'array'],
            'frequency' => ['required', Rule::enum(AlertFrequency::class)],
            'email' => ['boolean'],
            // E-mail alerts need explicit consent the first time.
            'email_consent' => ['exclude_unless:email,true,1', Rule::excludeIf(fn (): bool => (bool) $this->user()?->hasConsented(ConsentPurpose::EmailAlerts)), 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return JobFiltersParser::searchQuery((array) $this->validated('filters', []));
    }
}
