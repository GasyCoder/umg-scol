<?php

namespace App\Http\Requests\Corrections;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReviewCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('review', $this->route('correction'));
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['decision' => ['required', 'in:approve,reject'], 'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'min:10', 'max:500'], 'from_student' => ['sometimes', 'boolean']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['rejection_reason.required_if' => 'Indiquez le motif du refus.', 'rejection_reason.min' => 'Le motif doit comporter au moins dix caractères.', 'rejection_reason.max' => 'Le motif ne peut pas dépasser 500 caractères.'];
    }
}
