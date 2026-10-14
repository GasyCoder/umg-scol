<?php

namespace App\Http\Requests\StudentPortal;

use App\Enums\StudentCorrectionField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        // Field-specific rules are applied by the action, which reuses the manual-entry rules.
        return ['field' => ['required', 'string', Rule::enum(StudentCorrectionField::class)], 'value' => ['required', 'string', 'max:2000'], 'message' => ['nullable', 'string', 'max:500']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['field.required' => 'Choisissez l’information à corriger.', 'field.enum' => 'Cette information ne peut pas faire l’objet d’une demande de correction.', 'value.required' => 'Saisissez la valeur correcte.', 'value.max' => 'La valeur est trop longue.', 'message.max' => 'Le message ne peut pas dépasser 500 caractères.'];
    }
}
