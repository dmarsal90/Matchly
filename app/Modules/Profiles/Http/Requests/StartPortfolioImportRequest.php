<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Requests;

use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class StartPortfolioImportRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:255', 'url:https', function (string $attribute, mixed $value, Closure $fail): void {
                if (! app(PublicUrlGuard::class)->looksPublic((string) $value)) {
                    $fail(__('validation.url'));
                }
            }],
            // Explicit consent to read the public site (GDPR art. 7).
            'consent' => ['accepted'],
        ];
    }

    public function url(): string
    {
        return trim($this->string('url')->toString());
    }
}
