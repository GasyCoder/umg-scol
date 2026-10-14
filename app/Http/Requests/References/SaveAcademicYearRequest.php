<?php

namespace App\Http\Requests\References;

use App\Models\AcademicYear;
use Illuminate\Validation\Rule;

class SaveAcademicYearRequest extends ReferenceRequest
{
    protected function modelClass(): string
    {
        return AcademicYear::class;
    }

    protected function parameterName(): string
    {
        return 'academic_year';
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $record = $this->route('academic_year');
        $id = $record instanceof AcademicYear ? $record->id : null;

        return ['name' => ['required', 'string', 'max:30', Rule::unique('academic_years')->ignore($id)], 'starts_at' => ['nullable', 'date_format:Y-m-d'], 'ends_at' => ['nullable', 'date_format:Y-m-d', Rule::when(filled($this->input('starts_at')), 'after_or_equal:starts_at')], 'is_current' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean']];
    }
}
