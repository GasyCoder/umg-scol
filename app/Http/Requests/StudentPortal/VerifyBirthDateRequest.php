<?php

namespace App\Http\Requests\StudentPortal;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBirthDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['birth_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['birth_date.required' => 'Indiquez votre date de naissance.', 'birth_date.*' => 'Indiquez une date de naissance valide.'];
    }
}
