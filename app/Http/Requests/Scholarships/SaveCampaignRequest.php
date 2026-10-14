<?php

namespace App\Http\Requests\Scholarships;

use App\Models\ScholarshipCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', ScholarshipCampaign::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name')]);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')], 'name' => ['required', 'string', 'max:150'],
            'starts_at' => ['nullable', 'date_format:Y-m-d'], 'ends_at' => ['nullable', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['required' => 'Ce champ est obligatoire.', 'exists' => 'Année universitaire inconnue.', 'max' => 'Le texte dépasse la longueur autorisée (:max).', 'date_format' => 'La date doit respecter le format année-mois-jour.'];
    }
}
