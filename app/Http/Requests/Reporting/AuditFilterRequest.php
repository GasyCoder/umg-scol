<?php

namespace App\Http\Requests\Reporting;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AuditFilterRequest extends FormRequest
{
    public const KEYS = ['from', 'to', 'actor', 'module', 'event', 'subject'];

    public function authorize(): bool
    {
        return Gate::allows($this->routeIs('audits.export') ? 'export' : 'viewAny', Activity::class);
    }

    /**
     * Structured filters only: no free-text search in properties.
     *
     * @return array<string,array<mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'actor' => ['nullable', 'integer'], 'module' => ['nullable', 'string', 'max:50', 'regex:/^[a-z_]+$/'],
            'event' => ['nullable', 'string', 'max:100', 'regex:/^[a-z_.]+$/'], 'subject' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z]+$/'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['date_format' => 'Date invalide.', 'after_or_equal' => 'La date de fin doit suivre la date de début.', 'integer' => 'Filtre invalide.', 'regex' => 'Filtre invalide.', 'max' => 'Filtre invalide.'];
    }
}
