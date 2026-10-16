<?php

namespace App\Http\Requests\Imports;

use App\Models\StudentImport;
use App\Support\StudentImportColumns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class AnalyzeStudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('analyze', $this->route('student_import'));
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        /** @var StudentImport $import */
        $import = $this->route('student_import');

        $column = ['required', 'integer', 'min:0', 'max:'.(count($import->headers) - 1)];
        $rules = ['mapping' => ['required', 'array:'.implode(',', array_keys(StudentImportColumns::labels()))], 'mapping.*' => [...$column, 'distinct']];
        foreach (StudentImportColumns::required() as $field) {
            $rules['mapping.'.$field] = $column;
        }

        return $rules;
    }

    /** @return array<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $mapping = $this->input('mapping', []);
            if (is_array($mapping) && count($mapping) !== count(array_unique($mapping, SORT_REGULAR))) {
                $validator->errors()->add('mapping', 'Une colonne ne peut être utilisée deux fois.');
            }
            /** @var StudentImport $import */
            $import = $this->route('student_import');
            if ($import->establishment_id === null && is_array($mapping) && ! isset($mapping['establishment_code']) && ! isset($mapping['establishment'])) {
                $validator->errors()->add('mapping', 'Import pour tous les établissements : associez la colonne Code établissement (ou École / Institut).');
            }
        }];
    }

    public function messages(): array
    {
        $messages = ['distinct' => 'Une colonne ne peut être utilisée deux fois.', 'max' => 'La colonne sélectionnée est invalide.'];
        foreach (StudentImportColumns::required() as $field) {
            $messages['mapping.'.$field.'.required'] = 'Associez la colonne '.StudentImportColumns::labels()[$field].'.';
        }

        return $messages;
    }
}
