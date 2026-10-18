<?php

namespace App\Http\Requests\Students;

use App\Enums\StudentCorrectionField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NotifyStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('notify', $this->route('student'));
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [
            'fields' => ['present', 'array', 'max:20'],
            'fields.*' => ['string', 'distinct', Rule::in(array_column(StudentCorrectionField::cases(), 'value'))],
            'message' => ['nullable', 'string', 'max:1000'],
            'application' => ['nullable', 'string', 'uuid'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['fields.*' => 'Information inconnue.', 'message.max' => 'Le message ne peut pas dépasser 1000 caractères.'];
    }
}
