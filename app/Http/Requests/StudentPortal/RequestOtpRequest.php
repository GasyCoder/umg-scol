<?php

namespace App\Http\Requests\StudentPortal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255', 'regex:/^[\x20-\x7E]+$/']];
    }

    public function messages(): array
    {
        return ['email.email' => 'Adresse email invalide.', 'email.required' => 'Saisissez votre adresse email.'];
    }
}
