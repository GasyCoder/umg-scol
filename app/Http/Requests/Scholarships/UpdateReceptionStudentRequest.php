<?php

namespace App\Http\Requests\Scholarships;

use App\Http\Requests\Students\StudentInput;
use App\Models\Student;
use Illuminate\Support\Facades\Gate;

/** Identity completed at the reception counter: same rules as the student form. */
class UpdateReceptionStudentRequest extends StudentInput
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student && Gate::allows('updateAtReception', $student);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return $this->identityRules();
    }
}
