<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests\Settings;

use App\Modules\Identity\Application\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Passwordless (OAuth) accounts confirm by typing their e-mail instead.
        return $this->user()?->password !== null
            ? ['password' => $this->currentPasswordRules()]
            : ['email_confirmation' => ['required', 'string', 'in:'.$this->user()?->email]];
    }
}
