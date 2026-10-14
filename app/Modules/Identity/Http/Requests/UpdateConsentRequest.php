<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateConsentRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(ConsentPurpose::class)],
            'granted' => ['required', 'boolean'],
        ];
    }

    public function purpose(): ConsentPurpose
    {
        return ConsentPurpose::from($this->string('purpose')->toString());
    }
}
