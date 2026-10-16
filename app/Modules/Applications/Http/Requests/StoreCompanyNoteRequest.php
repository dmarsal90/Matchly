<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCompanyNoteRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:160'],
            'fact' => ['required', 'string', 'min:5', 'max:500'],
            // Only shown as a link, never fetched here; still no javascript: or internal addresses.
            'source_url' => ['nullable', 'string', 'max:2048', 'url:http,https', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(PublicUrlGuard::class)->looksPublic((string) $value)) {
                    $fail(__('validation.url'));
                }
            }],
        ];
    }

    public function company(): string
    {
        return trim($this->string('company')->toString());
    }

    public function fact(): string
    {
        return trim($this->string('fact')->toString());
    }

    public function sourceUrl(): ?string
    {
        return $this->filled('source_url') ? trim($this->string('source_url')->toString()) : null;
    }
}
