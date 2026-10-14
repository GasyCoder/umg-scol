<?php

namespace App\Http\Requests\Scholarships;

use App\Enums\ScholarshipCheckStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('review', $this->route('application'));
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['key' => ['required', 'string', 'max:50'], 'status' => ['required', Rule::enum(ScholarshipCheckStatus::class)], 'note' => ['nullable', 'string', 'max:500']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['required' => 'Ce champ est obligatoire.', 'enum' => 'Statut de contrôle invalide.', 'note.max' => 'La note ne peut pas dépasser 500 caractères.'];
    }
}
