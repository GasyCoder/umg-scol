<?php

namespace App\Http\Requests\Students;

use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Support\Facades\Gate;

class SaveStudentRecordRequest extends StudentInput
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->imposeEstablishment();
    }

    public function authorize(): bool
    {
        $record = $this->route('student_record');
        $student = $this->route('student');

        return $this->allowedEstablishment() && ($record instanceof StudentRecord ? Gate::allows('update', $record) : ($student instanceof Student && Gate::allows('create', [StudentRecord::class, $student])));
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return $this->recordRules();
    }
}
