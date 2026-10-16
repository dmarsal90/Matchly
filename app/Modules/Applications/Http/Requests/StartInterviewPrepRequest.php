<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartInterviewPrepRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'use_ai' => ['boolean'],
            // Sending the profile to an AI provider needs its explicit consent (unless already given).
            'ai_consent' => ['exclude_unless:use_ai,true,1', Rule::excludeIf(fn (): bool => (bool) $this->user()?->hasConsented(ConsentPurpose::AiProcessing)), 'accepted'],
        ];
    }
}
