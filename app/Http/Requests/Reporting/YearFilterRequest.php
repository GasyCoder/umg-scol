<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;

class YearFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['year' => ['nullable', 'integer', 'exists:academic_years,id']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['year.integer' => 'Année universitaire invalide.', 'year.exists' => 'Année universitaire inconnue.'];
    }

    public function yearId(): ?int
    {
        return $this->filled('year') ? (int) $this->validated('year') : null;
    }
}
