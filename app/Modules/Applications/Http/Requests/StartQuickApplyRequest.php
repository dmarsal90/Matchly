<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use App\Modules\Applications\Domain\Enums\LetterLength;
use App\Modules\Applications\Domain\Enums\LetterTone;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartQuickApplyRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tone' => ['required', Rule::enum(LetterTone::class)],
            'length' => ['required', Rule::enum(LetterLength::class)],
            'use_ai' => ['boolean'],
            // Sending the profile to an AI provider needs its explicit consent (unless already given).
            'ai_consent' => ['exclude_unless:use_ai,true,1', Rule::excludeIf(fn (): bool => (bool) $this->user()?->hasConsented(ConsentPurpose::AiProcessing)), 'accepted'],
        ];
    }

    public function tone(): LetterTone
    {
        return LetterTone::from((string) $this->validated('tone'));
    }

    public function length(): LetterLength
    {
        return LetterLength::from((string) $this->validated('length'));
    }
}
