<?php

namespace App\Support;

use App\Enums\RepeatCode;
use Illuminate\Support\Str;

class StudentNormalization
{
    public static function value(string $field, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return match ($field) {
            'email' => Str::lower($value),
            'cin' => preg_replace('/[\s.]+/u', '', $value),
            'phone' => self::phone($value),
            'sex' => self::sex($value),
            'nationality' => StudentReferenceLists::canonical(StudentReferenceLists::NATIONALITIES, $value),
            'bac_series' => StudentReferenceLists::canonical(StudentReferenceLists::BAC_SERIES, $value),
            'repeat_code' => RepeatCode::parse($value)->value ?? $value,
            'last_name', 'first_names', 'mother_name', 'scholarship_request_reference' => preg_replace('/\s+/u', ' ', $value),
            default => $value,
        };
    }

    /**
     * Malagasy numbers in one national form (0XXXXXXXXX), so that uniqueness compares like with like:
     * spaces removed, +261/261 prefix replaced, and the leading 0 that a numeric spreadsheet cell drops restored.
     */
    private static function phone(string $value): string
    {
        $value = (string) preg_replace('/[\s().-]+/u', '', $value);
        if (preg_match('/^(?:\+|00)?261(\d{9})$/', $value, $parts) === 1) {
            return '0'.$parts[1];
        }

        return preg_match('/^[2-9]\d{8}$/', $value) === 1 ? '0'.$value : $value;
    }

    private static function sex(string $value): string
    {
        return match (StudentImportColumns::fold($value)) {
            'm', 'h', 'masculin', 'homme', 'garcon' => 'M',
            'f', 'feminin', 'femme', 'fille' => 'F',
            default => $value,
        };
    }

    /** Standard Malagasy CIN: 12 digits. Other values (receipts, partial numbers) are kept but flagged. */
    public static function isStandardCin(?string $cin): bool
    {
        return $cin !== null && preg_match('/^\d{12}$/', $cin) === 1;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function data(array $data): array
    {
        foreach ($data as $field => $value) {
            $data[$field] = self::value($field, $value);
        }

        return $data;
    }
}
