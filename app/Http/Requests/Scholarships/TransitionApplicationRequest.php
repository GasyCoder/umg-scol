<?php

namespace App\Http\Requests\Scholarships;

use App\Enums\ScholarshipApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransitionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = ScholarshipApplicationStatus::tryFrom((string) $this->input('status'));

        // Unknown targets fall through to validation; known ones need their specific permission.
        return $target === null || Gate::allows('transition', [$this->route('application'), $target]);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['in_review', 'validated', 'needs_correction', 'rejected'])], 'reason' => ['required_if:status,needs_correction,rejected', 'nullable', 'string', 'min:10', 'max:1000']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['status.in' => 'Action inconnue.', 'reason.required_if' => 'Un motif est obligatoire.', 'reason.min' => 'Le motif doit comporter au moins dix caractères.', 'reason.max' => 'Le motif ne peut pas dépasser 1000 caractères.'];
    }
}
