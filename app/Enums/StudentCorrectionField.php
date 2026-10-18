<?php

namespace App\Enums;

use App\Http\Requests\Students\CreateStudentRequest;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * Whitelist of Student fields a linked student may ask to correct.
 * Annual declarations (level, program, repeat code, scholarship request) are deliberately excluded.
 */
enum StudentCorrectionField: string
{
    case LastName = 'last_name';
    case FirstNames = 'first_names';
    case Sex = 'sex';
    case BirthDate = 'birth_date';
    case MotherName = 'mother_name';
    case Cin = 'cin';
    case CinIssuedAt = 'cin_issued_at';
    case CinIssuedPlace = 'cin_issued_place';
    case Nationality = 'nationality';
    case Phone = 'phone';
    case Email = 'email';
    case Address = 'address';
    case BacSeries = 'bac_series';
    case BacYear = 'bac_year';

    public function label(): string
    {
        return match ($this) {
            self::LastName => 'Nom',
            self::FirstNames => 'Prénoms',
            self::Sex => 'Sexe',
            self::BirthDate => 'Date de naissance',
            self::MotherName => 'Nom et prénoms de la mère',
            self::Cin => 'Numéro CIN',
            self::CinIssuedAt => 'Date de délivrance CIN',
            self::CinIssuedPlace => 'Lieu de délivrance CIN',
            self::Nationality => 'Nationalité',
            self::Phone => 'Téléphone',
            self::Email => 'Email de contact',
            self::Address => 'Adresse',
            self::BacSeries => 'Série BAC',
            self::BacYear => 'Année BAC',
        };
    }

    /**
     * Fields the student changes directly, without validation (ADR-089): sex, nationality and address.
     * Nationality decides the scholarship eligibility: the staff is told of each change (StudentActivity).
     * Names, birth date, mother, CIN, phone, contact email and BAC always wait for the staff's decision.
     */
    public function appliesDirectly(): bool
    {
        return in_array($this, [self::Sex, self::Nationality, self::Address], true);
    }

    /** CIN data is never returned to the student portal, so its values stay hidden there. */
    public function hiddenFromStudent(): bool
    {
        return in_array($this, [self::Cin, self::CinIssuedAt, self::CinIssuedPlace], true);
    }

    /**
     * Same rules as manual entry; a correction always proposes a value.
     *
     * @return array<mixed>
     */
    public function rules(): array
    {
        $rules = (new CreateStudentRequest)->importIdentityRules()[$this->value];

        return ['required', ...array_values(array_filter($rules, fn ($rule) => $rule !== 'nullable' && $rule !== 'required'))];
    }

    /** Official value as a comparable string (dates Y-m-d), null when empty. */
    public function valueOf(Student $student): ?string
    {
        $value = $student->getAttribute($this->value);

        return match (true) {
            $value === null => null,
            $value instanceof CarbonInterface => $value->format('Y-m-d'),
            $value instanceof \BackedEnum => (string) $value->value,
            default => (string) $value,
        };
    }

    /** Keyed fingerprint of the observed official value: detects later changes without storing it. */
    public function fingerprint(Student $student): string
    {
        $value = $this->valueOf($student);

        return hash_hmac('sha256', $this->value.'|'.($value === null ? 'null' : 'value:'.$value), (string) config('app.key'));
    }
}
