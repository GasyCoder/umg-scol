<?php

namespace App\Http\Requests\Students;

use App\Models\Student;
use Illuminate\Support\Facades\Gate;

class CreateStudentRequest extends StudentInput
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->imposeEstablishment();
    }

    public function authorize(): bool
    {
        return Gate::allows('create', Student::class) && $this->allowedEstablishment();
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [...$this->identityRules(), ...$this->recordRules()];
    }
}
