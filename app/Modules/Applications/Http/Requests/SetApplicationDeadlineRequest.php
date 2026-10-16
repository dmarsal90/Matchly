<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class SetApplicationDeadlineRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['deadline' => ['nullable', 'date_format:Y-m-d']];
    }

    public function deadline(): ?CarbonImmutable
    {
        return $this->filled('deadline') ? CarbonImmutable::parse($this->string('deadline')->toString())->startOfDay() : null;
    }
}
