<?php

namespace App\Support;

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
            'cin' => preg_replace('/\s+/u', '', $value),
            'phone' => preg_replace('/[\s().-]+/u', '', $value),
            'student_number', 'bac_registration_number' => preg_replace('/\s+/u', ' ', $value),
            default => $value,
        };
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
