<?php

namespace App\Http\Requests\StudentPortal;

use App\Support\StudentNormalization;
use Illuminate\Foundation\Http\FormRequest;

class LinkStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(StudentNormalization::data($this->all()));
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['cin' => ['required', 'string', 'max:100'], 'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']];
    }

    public function messages(): array
    {
        return ['required' => 'Ce champ est obligatoire.', 'date_format' => 'Saisissez une date valide.', 'before_or_equal' => 'La date ne peut pas être future.'];
    }
}
