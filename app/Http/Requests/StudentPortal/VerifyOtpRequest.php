<?php

namespace App\Http\Requests\StudentPortal;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['challenge_id' => ['required', 'uuid'], 'code' => ['required', 'string', 'regex:/^[0-9]{6}$/']];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Saisissez les six chiffres du code.', 'required' => 'Ce champ est obligatoire.'];
    }
}
