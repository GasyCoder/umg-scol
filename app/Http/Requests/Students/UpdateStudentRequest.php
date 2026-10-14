<?php

namespace App\Http\Requests\Students;

use App\Models\Student;
use Illuminate\Support\Facades\Gate;

class UpdateStudentRequest extends StudentInput
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student && Gate::allows('update', $student);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return $this->identityRules();
    }
}
