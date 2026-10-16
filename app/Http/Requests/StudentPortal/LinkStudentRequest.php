<?php

namespace App\Http\Requests\StudentPortal;

use App\Actions\StudentPortal\LinkStudent;
use App\Support\StudentNormalization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // Not every student has a CIN: the phone, the email or the last name declared by the establishment work too.
        return ['identifier_type' => ['required', Rule::in(LinkStudent::IDENTIFIERS)], 'identifier' => ['required', 'string', 'max:150'],
            // With the last name only, the mother's name of the record is asked too.
            'mother_name' => ['nullable', 'required_if:identifier_type,last_name', 'string', 'max:255'], 'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']];
    }

    public function messages(): array
    {
        return ['required_if' => 'Indiquez le nom de votre mère.', 'in' => 'Choisissez comment vous identifier.', 'required' => 'Ce champ est obligatoire.', 'date_format' => 'Saisissez une date valide.', 'before_or_equal' => 'La date ne peut pas être future.'];
    }
}
