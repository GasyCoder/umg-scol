<?php

namespace App\Http\Requests\Scholarships;

use App\Models\ScholarshipApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** A decision taken on several files of the list; each file is authorized again by the action. */
class BulkTransitionApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', ScholarshipApplication::class);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['in_review', 'validated', 'needs_correction', 'rejected'])],
            'applications' => ['required', 'array', 'min:1', 'max:100'],
            'applications.*' => ['required', 'string', 'uuid', 'distinct'],
            'reason' => ['required_if:status,needs_correction,rejected', 'nullable', 'string', 'min:10', 'max:1000'],
            // « Demander une correction » : also email the student (ADR-088), when the account may.
            'notify_student' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['status.in' => 'Action inconnue.', 'applications.required' => 'Sélectionnez au moins un dossier.', 'applications.max' => 'Cent dossiers au plus par décision.', 'applications.*' => 'Sélection invalide.', 'reason.required_if' => 'Un motif est obligatoire.', 'reason.min' => 'Le motif doit comporter au moins dix caractères.', 'reason.max' => 'Le motif ne peut pas dépasser 1000 caractères.'];
    }
}
