<?php

namespace App\Http\Requests\StudentPortal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReviewLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('review', $this->route('student_link_request'));
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['decision' => ['required', 'in:approve,reject'], 'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'min:10', 'max:500']];
    }

    public function messages(): array
    {
        return ['rejection_reason.required_if' => 'Indiquez un motif interne.', 'rejection_reason.min' => 'Le motif doit comporter au moins dix caractères.'];
    }
}
