<?php

namespace App\Enums;

use App\Http\Requests\Students\CreateStudentRequest;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * Whitelist of Student fields a linked student may ask to correct.
 * student_number and annual declarations are deliberately excluded.
 */
enum StudentCorrectionField: string
{
    case LastName = 'last_name';
    case FirstNames = 'first_names';
    case BirthDate = 'birth_date';
    case BirthPlace = 'birth_place';
    case Cin = 'cin';
    case CinIssuedAt = 'cin_issued_at';
    case CinIssuedPlace = 'cin_issued_place';
    case Phone = 'phone';
    case Email = 'email';
    case Address = 'address';
    case BacRegistrationNumber = 'bac_registration_number';
    case BacSeries = 'bac_series';
    case BacYear = 'bac_year';
    case BacMention = 'bac_mention';

    public function label(): string
    {
        return match ($this) {
            self::LastName => 'Nom',
            self::FirstNames => 'Prénoms',
            self::BirthDate => 'Date de naissance',
            self::BirthPlace => 'Lieu de naissance',
            self::Cin => 'Numéro CIN',
            self::CinIssuedAt => 'Date de délivrance CIN',
            self::CinIssuedPlace => 'Lieu de délivrance CIN',
            self::Phone => 'Téléphone',
            self::Email => 'Email de contact',
            self::Address => 'Adresse',
            self::BacRegistrationNumber => 'Numéro BAC',
            self::BacSeries => 'Série BAC',
            self::BacYear => 'Année BAC',
            self::BacMention => 'Mention BAC',
        };
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
