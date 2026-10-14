<?php

namespace App\Http\Requests\Students;

use App\Enums\StudentRecordStatus;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('student_record');
        $student = $this->route('student');

        return $record instanceof StudentRecord ? Gate::allows('update', $record) : ($student instanceof Student && Gate::allows('deactivate', $student));
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return $this->route('student_record') instanceof StudentRecord ? ['status' => ['required', Rule::enum(StudentRecordStatus::class)]] : ['is_active' => ['required', 'boolean']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['required' => 'Le statut est obligatoire.', 'boolean' => 'Le statut d’identité est invalide.', 'enum' => 'Le statut de déclaration doit être active ou inactive.'];
    }
}
