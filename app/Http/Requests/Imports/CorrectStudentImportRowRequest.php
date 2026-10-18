<?php

namespace App\Http\Requests\Imports;

use App\Enums\StudentImportStatus;
use App\Models\StudentImport;
use App\Support\StudentImportColumns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Corrected values of a preview row: only the fields associated with a column of the file. */
class CorrectStudentImportRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $import = $this->route('student_import');

        // Before confirmation the file is analysed again; afterwards the row is imported alone.
        return $import instanceof StudentImport && Gate::allows($import->status === StudentImportStatus::Completed ? 'commit' : 'analyze', $import);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        /** @var StudentImport $import */
        $import = $this->route('student_import');

        return [
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'string', 'max:255'],
            'values_keys' => ['array'],
            'values_keys.*' => [Rule::in(array_keys($import->mapping ?? []))],
            // Fields of the existing record to replace with the file value.
            'identity_updates' => ['nullable', 'array'],
            'identity_updates.*' => ['string', 'distinct', Rule::in(array_values(array_intersect(array_keys($import->mapping ?? []), StudentImportColumns::IDENTITY_FIELDS)))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = $this->input('values');
        $this->merge(['values_keys' => is_array($values) ? array_keys($values) : []]);
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['values.required' => 'Aucune valeur à corriger.', 'values.*.max' => 'Une valeur dépasse 255 caractères.', 'values_keys.*.in' => 'Ce champ n’est associé à aucune colonne du fichier.', 'identity_updates.*.in' => 'Ce champ de la fiche ne peut pas être mis à jour depuis l’import.'];
    }
}
