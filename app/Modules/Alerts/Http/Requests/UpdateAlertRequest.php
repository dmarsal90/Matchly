<?php

declare(strict_types=1);

namespace App\Modules\Alerts\Http\Requests;

use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Jobs\Application\Queries\JobFiltersParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAlertRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'filters' => ['sometimes', 'array'],
            'frequency' => ['sometimes', Rule::enum(AlertFrequency::class)],
            'email' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'email_consent' => ['exclude_unless:email,true,1', Rule::excludeIf(fn (): bool => (bool) $this->user()?->hasConsented(ConsentPurpose::EmailAlerts)), 'accepted'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = $this->safe()->only(['name', 'frequency', 'email', 'active']);
        if ($this->has('filters')) {
            $changes['filters'] = JobFiltersParser::searchQuery((array) $this->validated('filters', []));
        }

        return $changes;
    }
}
