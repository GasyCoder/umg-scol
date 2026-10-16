<?php

namespace App\Http\Requests\Imports;

use App\Models\StudentImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UploadStudentImportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->user()?->hasRole('establishment') && ! $this->has('establishment_id')) {
            $this->merge(['establishment_id' => $this->user()->establishment_id]);
        }
    }

    public function authorize(): bool
    {
        if (! Gate::allows('create', StudentImport::class)) {
            return false;
        }
        if (! $this->user()->hasRole('establishment')) {
            return true;
        }
        $value = $this->input('establishment_id');

        return (is_string($value) || is_int($value)) && (string) $value === (string) $this->user()->establishment_id;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'extensions:xlsx,csv', 'mimes:xlsx,csv,txt', 'max:'.config('student_imports.max_kilobytes')], // Empty for a global actor: the file covers several establishments, named on each row.
            'establishment_id' => [$this->user()->hasRole('establishment') ? 'required' : 'nullable', 'integer', Rule::exists('establishments', 'id')->whereNull('deleted_at')->where('is_active', true)], 'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->whereNull('deleted_at')->where('is_active', true)]];
    }

    public function messages(): array
    {
        return ['required' => 'Le champ :attribute est obligatoire.', 'exists' => 'Le référentiel est invalide ou inactif.', 'file.max' => 'Le fichier dépasse la taille maximale autorisée.', 'file.mimes' => 'Seuls les fichiers XLSX et CSV sont acceptés.', 'file.extensions' => 'Seules les extensions .xlsx et .csv sont acceptées.'];
    }
}
