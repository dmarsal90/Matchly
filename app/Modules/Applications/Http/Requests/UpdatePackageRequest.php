<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePackageRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cover_letter' => ['nullable', 'string', 'max:8000'],
            // Shape and lengths are enforced by ResumeData::fromArray().
            'resume' => ['nullable', 'array'],
            'resume.summary' => ['nullable', 'string', 'max:1200'],
            'resume.skills' => ['array', 'max:40'],
            'resume.skills.*' => ['string', 'max:60'],
            'resume.experience' => ['array', 'max:20'],
            'resume.experience.*.bullets' => ['array', 'max:10'],
            'resume.experience.*.bullets.*' => ['string', 'max:400'],
        ];
    }
}
