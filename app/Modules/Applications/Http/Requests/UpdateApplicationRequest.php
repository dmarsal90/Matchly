<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateApplicationRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ApplicationStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::from($this->string('status')->toString());
    }

    public function notes(): ?string
    {
        return $this->filled('notes') ? $this->string('notes')->trim()->toString() : null;
    }
}
