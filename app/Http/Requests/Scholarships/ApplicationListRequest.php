<?php

namespace App\Http\Requests\Scholarships;

use App\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ApplicationListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows($this->routeIs('scholarships.applications.export') ? 'export' : 'viewAny', ScholarshipApplication::class);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'], 'campaign_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::enum(ScholarshipApplicationStatus::class)],
            'establishment_id' => ['nullable', 'integer'], 'level_id' => ['nullable', 'integer'],
            'received_from' => ['nullable', 'date_format:Y-m-d'], 'received_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:received_from'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['max' => 'La recherche doit contenir au plus :max caractères.', 'integer' => 'Filtre invalide.', 'enum' => 'Statut de filtre invalide.', 'date_format' => 'Date invalide.', 'after_or_equal' => 'La date de fin doit suivre la date de début.'];
    }
}
