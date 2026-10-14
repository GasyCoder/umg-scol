<?php

namespace App\Http\Requests\Students;

use App\Support\StudentNormalization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class StudentInput extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(StudentNormalization::data($this->all()));
    }

    /** @return array<string, array<mixed>> */
    public function importIdentityRules(): array
    {
        return $this->identityRules();
    }

    /** @return array<string,array<mixed>> */
    protected function identityRules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:150'], 'first_names' => ['nullable', 'string', 'max:150'],
            'student_number' => ['nullable', 'string', 'max:100'], 'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_place' => ['nullable', 'string', 'max:255'], 'cin' => ['nullable', 'string', 'max:100'],
            'cin_issued_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'cin_issued_place' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9]+$/'], 'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'], 'bac_registration_number' => ['nullable', 'string', 'max:100'],
            'bac_series' => ['nullable', 'string', 'max:50'], 'bac_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'bac_mention' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, array<mixed>> */
    protected function recordRules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'establishment_id' => ['required', 'integer', Rule::exists('establishments', 'id')],
            'level_id' => ['required', 'integer', Rule::exists('levels', 'id')],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')->where('establishment_id', $this->input('establishment_id'))],
        ];
    }

    protected function allowedEstablishment(): bool
    {
        if (! $this->user()->hasRole('establishment') || ! $this->filled('establishment_id')) {
            return true;
        }
        $value = $this->input('establishment_id');

        return (is_string($value) || is_int($value)) && (string) $value === (string) $this->user()->establishment_id;
    }

    protected function imposeEstablishment(): void
    {
        if ($this->user()?->hasRole('establishment') && ! $this->filled('establishment_id')) {
            $this->merge(['establishment_id' => $this->user()->establishment_id]);
        }
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['required' => 'Le champ :attribute est obligatoire.', 'exists' => 'Le référentiel sélectionné est invalide ou le parcours appartient à un autre établissement.', 'max' => 'Le champ :attribute dépasse la limite autorisée (:max).', 'min' => 'La valeur :attribute est trop petite.', 'date_format' => 'La date doit respecter le format année-mois-jour.', 'before_or_equal' => 'La date ne peut pas être future.', 'email' => 'L’adresse email est invalide.', 'integer' => 'Le champ :attribute doit être un entier.', 'regex' => 'Le téléphone doit contenir des chiffres et éventuellement un + initial.', 'boolean' => 'Le statut est invalide.'];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return ['last_name' => 'nom', 'first_names' => 'prénoms', 'student_number' => 'matricule', 'birth_date' => 'date de naissance', 'phone' => 'téléphone', 'academic_year_id' => 'année universitaire', 'establishment_id' => 'établissement', 'level_id' => 'niveau', 'program_id' => 'parcours'];
    }
}
