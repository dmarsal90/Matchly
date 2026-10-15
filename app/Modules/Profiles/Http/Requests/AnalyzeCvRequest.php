<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AnalyzeCvRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'use_ai' => ['boolean'],
            // Sending the CV to an AI provider needs its own explicit consent.
            'ai_consent' => ['exclude_unless:use_ai,true,1', 'accepted'],
        ];
    }
}
