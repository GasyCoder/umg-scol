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

        return ['mapping' => ['required', 'array:'.implode(',', array_keys(StudentImportColumns::labels()))], 'mapping.*' => ['required', 'integer', 'min:0', 'max:'.(count($import->headers) - 1), 'distinct'], 'mapping.last_name' => ['required', 'integer', 'min:0', 'max:'.(count($import->headers) - 1)], 'mapping.level' => ['required', 'integer', 'min:0', 'max:'.(count($import->headers) - 1)]];
    }

    /** @return array<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $mapping = $this->input('mapping', []);
            if (is_array($mapping) && count($mapping) !== count(array_unique($mapping, SORT_REGULAR))) {
                $validator->errors()->add('mapping', 'Une colonne ne peut être utilisée deux fois.');
            }
        }];
    }

    public function messages(): array
    {
        return ['mapping.last_name.required' => 'Associez la colonne Nom.', 'mapping.level.required' => 'Associez la colonne Niveau.', 'distinct' => 'Une colonne ne peut être utilisée deux fois.', 'max' => 'La colonne sélectionnée est invalide.'];
    }
}
