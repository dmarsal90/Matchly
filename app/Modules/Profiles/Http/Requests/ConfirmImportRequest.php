<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Http\Requests;

use App\Modules\Profiles\Domain\Data\ImportSelection;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfirmImportRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'skills' => ['present', 'array', 'max:200'],
            'skills.*' => ['string', 'max:64'],
            'items' => ['array'],
            'items.positions' => ['array'],
            'items.education' => ['array'],
            'items.certifications' => ['array'],
            'items.projects' => ['array'],
            'items.*.*' => ['integer', 'min:0'],
            'headline' => ['nullable', 'string', 'max:120'],
            'languages' => ['array', 'max:15'],
            'languages.*.code' => ['required', 'string', 'regex:/^[a-z]{2,3}$/', 'distinct'],
            'languages.*.level' => ['required', Rule::enum(LanguageLevel::class)],
        ];
    }

    public function selection(): ImportSelection
    {
        /** @var array<string, list<int|string>> $items */
        $items = $this->validated('items', []);
        /** @var list<array{code: string, level: string}> $languages */
        $languages = $this->validated('languages', []);

        return new ImportSelection(
            skills: array_values(array_map('strval', (array) $this->validated('skills', []))),
            items: array_map(fn (array $indexes): array => array_map('intval', $indexes), $items),
            headline: is_string($this->validated('headline')) ? $this->validated('headline') : null,
            languages: array_map(fn (array $language): array => ['code' => $language['code'], 'level' => $language['level']], $languages),
        );
    }
}
