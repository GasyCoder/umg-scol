<?php

namespace App\Http\Requests\Students;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Student::class) && (! $this->user()->hasRole('establishment') || ! $this->filled('establishment_id') || $this->integer('establishment_id') === $this->user()->establishment_id);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')], 'establishment_id' => ['nullable', 'integer', Rule::exists('establishments', 'id')], 'level_id' => ['nullable', 'integer', Rule::exists('levels', 'id')], 'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')], 'status' => ['nullable', Rule::in(['active', 'inactive'])]];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['string' => 'La recherche doit être du texte.', 'max' => 'La recherche doit contenir au plus :max caractères.', 'integer' => 'Le filtre doit être un identifiant entier.', 'exists' => 'Le référentiel du filtre n’existe pas.', 'in' => 'Le statut de filtre est invalide.'];
    }
}
