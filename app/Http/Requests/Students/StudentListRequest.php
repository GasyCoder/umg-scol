<?php

namespace App\Http\Requests\Students;

use App\Enums\SituationSource;
use App\Enums\SituationVerification;
use App\Enums\StudentRecordStatus;
use App\Models\Student;
use App\Support\Students\StudentBrowser;
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
        // academic_year_id: an id, or « all » for every year (absent = current year).
        $year = $this->input('academic_year_id') === StudentBrowser::ALL_YEARS ? [Rule::in([StudentBrowser::ALL_YEARS])] : ['integer', Rule::exists('academic_years', 'id')->whereNull('deleted_at')];

        return ['search' => ['nullable', 'string', 'max:100'], 'academic_year_id' => ['nullable', ...$year], 'establishment_id' => ['nullable', 'integer', Rule::exists('establishments', 'id')->whereNull('deleted_at')], 'level_id' => ['nullable', 'integer', Rule::exists('levels', 'id')->whereNull('deleted_at')], 'mention_id' => ['nullable', 'regex:/^(none|[1-9][0-9]*)$/'], 'program_id' => ['nullable', 'regex:/^(none|[1-9][0-9]*)$/'], 'status' => ['nullable', Rule::enum(StudentRecordStatus::class)], 'holder' => ['nullable', Rule::in(['yes', 'no'])], 'verification' => ['nullable', Rule::enum(SituationVerification::class)], 'source' => ['nullable', Rule::enum(SituationSource::class)], 'view' => ['nullable', Rule::in(['list'])],
            // Export of the rows selected in the list (sent by POST: the list can be long).
            'records' => ['nullable', 'array', 'max:500'], 'records.*' => ['string', 'uuid']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['string' => 'La recherche doit être du texte.', 'max' => 'La recherche doit contenir au plus :max caractères.', 'integer' => 'Le filtre doit être un identifiant entier.', 'exists' => 'Le référentiel du filtre n’existe pas.', 'in' => 'La valeur du filtre est invalide.', 'regex' => 'Le filtre de mention ou de parcours est invalide.'];
    }
}
