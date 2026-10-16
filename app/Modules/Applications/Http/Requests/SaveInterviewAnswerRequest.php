<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http\Requests;

use App\Modules\Applications\Application\Actions\SaveInterviewAnswer;
use Illuminate\Foundation\Http\FormRequest;

final class SaveInterviewAnswerRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'size:40', 'regex:/^[0-9a-f]{40}$/'],
            'answer' => ['nullable', 'string', 'max:'.SaveInterviewAnswer::MAX_LENGTH],
        ];
    }

    public function key(): string
    {
        return $this->string('key')->toString();
    }

    public function answer(): string
    {
        return $this->string('answer')->toString();
    }
}
