<?php

namespace App\Http\Requests\Students;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Action applied to the situations ticked in the student list; each one is still authorised on its own. */
class BulkStudentRecordsRequest extends FormRequest
{
    public const ACTIONS = ['deactivate', 'activate', 'trash', 'verify'];

    public function authorize(): bool
    {
        return Gate::allows('viewAny', Student::class);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(self::ACTIONS)], 'records' => ['required', 'array', 'min:1', 'max:200'], 'records.*' => ['required', 'string', 'uuid', 'distinct']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['action.in' => 'Action groupée inconnue.', 'records.required' => 'Sélectionnez au moins un étudiant.', 'records.max' => 'Sélectionnez au plus :max étudiants à la fois.', 'uuid' => 'Sélection invalide.'];
    }
}
