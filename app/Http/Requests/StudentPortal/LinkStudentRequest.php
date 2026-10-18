<?php

namespace App\Http\Requests\StudentPortal;

use Illuminate\Foundation\Http\FormRequest;

class LinkStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        // Answers are normalized per question type when compared (StudentIdentityQuestions).
        return ['answers' => ['required', 'array', 'min:1', 'max:3'], 'answers.*' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today']];
    }

    public function messages(): array
    {
        return ['answers.required' => 'Répondez aux questions.', 'required' => 'Ce champ est obligatoire.', 'date_format' => 'Saisissez une date valide.', 'before_or_equal' => 'La date ne peut pas être future.'];
    }
}
