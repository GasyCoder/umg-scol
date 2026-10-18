<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;

/** Selection of the student list or of the scholarship file list; each student is authorized again by the action. */
class BulkNotifyStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('students.notify') === true;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [
            // Exactly one kind of selection: students, annual situations (student list) or scholarship files.
            'students' => ['required_without_all:records,applications', 'prohibits:records,applications', 'array', 'min:1', 'max:100'],
            'students.*' => ['required', 'string', 'uuid', 'distinct'],
            'records' => ['required_without_all:students,applications', 'prohibits:applications', 'array', 'min:1', 'max:100'],
            'records.*' => ['required', 'string', 'uuid', 'distinct'],
            'applications' => ['required_without_all:students,records', 'array', 'min:1', 'max:100'],
            'applications.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['required_without_all' => 'Sélectionnez au moins un étudiant.', 'max' => 'Cent étudiants au plus par envoi.', '*.uuid' => 'Sélection invalide.'];
    }
}
