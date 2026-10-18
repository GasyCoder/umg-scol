<?php

namespace App\Http\Requests\Students;

use App\Enums\RepeatCode;
use App\Enums\StudentSex;
use App\Support\StudentNormalization;
use App\Support\StudentReferenceLists;
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
        // UIN and bank account belong to the central office: never accepted from another account.
        $central = $this->user() !== null && ! $this->user()->seesScholarshipRegistry() ? ['prohibited'] : [];

        return [
            'last_name' => ['required', 'string', 'max:150'], 'first_names' => ['nullable', 'string', 'max:150'],
            'sex' => ['required', Rule::enum(StudentSex::class)],
            'birth_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            // Any value is accepted; a CIN other than 12 digits is flagged as non-standard.
            'cin' => ['nullable', 'string', 'max:100'],
            'cin_issued_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'], 'cin_issued_place' => ['nullable', 'string', 'max:255'],
            'nationality' => ['required', Rule::in(StudentReferenceLists::NATIONALITIES)],
            // PNES identifiers: digits and letters only once spaces are removed.
            'uin' => [...$central, 'nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z]+$/'], 'scholarship_code' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z-]+$/'],
            'bank_account' => [...$central, 'nullable', 'string', 'max:34', 'regex:/^[0-9A-Za-z]+$/'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9]+$/'], 'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'bac_series' => ['nullable', Rule::in(StudentReferenceLists::BAC_SERIES)], 'bac_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
        ];
    }

    /** @return array<string, array<mixed>> */
    protected function recordRules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->whereNull('deleted_at')],
            'establishment_id' => ['required', 'integer', Rule::exists('establishments', 'id')->whereNull('deleted_at')],
            'level_id' => ['required', 'integer', Rule::exists('levels', 'id')->whereNull('deleted_at')],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')->whereNull('deleted_at')->where('establishment_id', $this->input('establishment_id'))],
            ...self::declarationRules(),
        ];
    }

    /**
     * Declared situation of the establishment file, shared with the import.
     *
     * @return array<string, array<mixed>>
     */
    public static function declarationRules(): array
    {
        return [
            'repeat_code' => ['required', Rule::enum(RepeatCode::class)],
            'scholarship_requested_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'scholarship_request_reference' => ['nullable', 'string', 'max:50'],
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
        return ['uin.regex' => 'L’UIN ne doit contenir que des chiffres et des lettres.', 'scholarship_code.regex' => 'Le code bourse ne doit contenir que des chiffres, des lettres et des tirets.', 'bank_account.regex' => 'Le numéro de compte ne doit contenir que des chiffres et des lettres.', 'prohibited' => 'Le champ :attribute est réservé à la Scolarité Centrale.', 'required' => 'Le champ :attribute est obligatoire.', 'exists' => 'Le référentiel sélectionné est invalide ou le parcours appartient à un autre établissement.', 'max' => 'Le champ :attribute dépasse la limite autorisée (:max).', 'min' => 'La valeur :attribute est trop petite.', 'date_format' => 'La date doit respecter le format année-mois-jour.', 'before_or_equal' => 'La date ne peut pas être future.', 'after_or_equal' => 'La date est antérieure à 1900 : vérifiez l’année saisie.', 'email' => 'L’adresse email est invalide.', 'integer' => 'Le champ :attribute doit être un entier.', 'regex' => 'Le téléphone doit contenir des chiffres et éventuellement un + initial.', 'boolean' => 'Le statut est invalide.', 'in' => 'La valeur :attribute ne fait pas partie de la liste du modèle.', 'enum' => 'La valeur :attribute ne fait pas partie de la liste du modèle.', 'required_with' => 'La référence de la demande est obligatoire lorsque la date de dépôt est renseignée.'];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return ['last_name' => 'nom', 'first_names' => 'prénoms', 'sex' => 'sexe', 'birth_date' => 'date de naissance', 'mother_name' => 'nom de la mère', 'nationality' => 'nationalité', 'uin' => 'UIN', 'scholarship_code' => 'code bourse', 'bank_account' => 'numéro de compte', 'bac_series' => 'série du BAC', 'bac_year' => 'année du BAC', 'repeat_code' => 'code de redoublement', 'scholarship_requested_at' => 'date de dépôt de la demande', 'scholarship_request_reference' => 'référence de la demande', 'phone' => 'téléphone', 'academic_year_id' => 'année universitaire', 'establishment_id' => 'établissement', 'level_id' => 'niveau', 'program_id' => 'parcours'];
    }
}
