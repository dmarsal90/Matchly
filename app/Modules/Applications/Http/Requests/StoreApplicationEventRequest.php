<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Only the entries a person can record by hand; status changes go through the status form. */
final class StoreApplicationEventRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([ApplicationEventType::Interview->value, ApplicationEventType::Note->value, ApplicationEventType::FollowUp->value])],
            'note' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:+1 year', 'after_or_equal:-5 years'],
        ];
    }

    public function type(): ApplicationEventType
    {
        return ApplicationEventType::from($this->string('type')->toString());
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->trim()->toString() : null;
    }

    public function occurredAt(): CarbonImmutable
    {
        return $this->filled('occurred_at') ? CarbonImmutable::parse($this->string('occurred_at')->toString()) : CarbonImmutable::now();
    }
}
