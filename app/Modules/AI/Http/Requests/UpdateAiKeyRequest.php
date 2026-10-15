<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAiKeyRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Anthropic keys look like sk-ant-…; the key is stored encrypted and never shown again.
            'anthropic_api_key' => ['required', 'string', 'min:20', 'max:300', 'regex:/^sk-ant-[A-Za-z0-9_\-]+$/'],
        ];
    }
}
