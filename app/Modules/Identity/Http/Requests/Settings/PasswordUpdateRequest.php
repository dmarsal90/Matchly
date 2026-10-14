<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests\Settings;

use App\Modules\Identity\Application\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Accounts created through OAuth have no password yet: they set a first one.
            'current_password' => $this->user()?->password !== null ? $this->currentPasswordRules() : ['nullable'],
            'password' => $this->passwordRules(),
        ];
    }
}
