<?php

namespace App\Http\Requests\Scholarships;

use App\Models\ScholarshipApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReceiveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('receive', ScholarshipApplication::class);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['student_id' => ['required', 'integer'], 'ddb_number' => ['nullable', 'string', 'max:100'], 'internal_notes' => ['nullable', 'string', 'max:2000']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['required' => 'Sélectionnez l’étudiant.', 'integer' => 'Sélection invalide.', 'ddb_number.max' => 'Le numéro DDB dépasse 100 caractères.', 'internal_notes.max' => 'Les observations dépassent 2000 caractères.'];
    }
}
