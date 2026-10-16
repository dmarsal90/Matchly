<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class DiscoverCompanyFactsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:160'],
            'site_url' => ['required', 'string', 'max:2048', 'url:http,https', function (string $attribute, mixed $value, Closure $fail): void {
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

    public function siteUrl(): string
    {
        return trim($this->string('site_url')->toString());
    }
}
