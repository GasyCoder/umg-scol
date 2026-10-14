<?php

namespace App\Http\Requests\Corrections;

use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Models\StudentCorrectionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CorrectionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', StudentCorrectionRequest::class) && (! $this->user()->hasRole('establishment') || ! $this->filled('establishment_id') || $this->integer('establishment_id') === $this->user()->establishment_id);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['all', ...array_column(StudentCorrectionStatus::cases(), 'value')])], 'field' => ['nullable', Rule::enum(StudentCorrectionField::class)], 'establishment_id' => ['nullable', 'integer', Rule::exists('establishments', 'id')]];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['max' => 'La recherche doit contenir au plus :max caractères.', 'enum' => 'Le filtre est invalide.', 'in' => 'Le statut de filtre est invalide.', 'integer' => 'Le filtre doit être un identifiant entier.', 'exists' => 'L’établissement du filtre n’existe pas.'];
    }
}
