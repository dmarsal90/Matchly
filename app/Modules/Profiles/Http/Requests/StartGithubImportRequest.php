<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Requests;

use App\Modules\Profiles\Domain\Data\GitHubUsername;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class StartGithubImportRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (GitHubUsername::tryParse(is_string($value) ? $value : null) === null) {
                    $fail(__('skills.github.invalid_username'));
                }
            }],
            // Explicit consent to read public GitHub data (GDPR art. 7).
            'consent' => ['accepted'],
        ];
    }

    public function username(): GitHubUsername
    {
        return GitHubUsername::parse((string) $this->validated('username'));
    }
}
