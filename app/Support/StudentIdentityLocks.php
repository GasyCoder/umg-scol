<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class StudentIdentityLocks
{
    // UIN and code bourse (PNES) identify one student too.
    public const UNIQUE_FIELDS = ['cin', 'phone', 'email', 'uin', 'scholarship_code'];

    /**
     * @param  array<string,mixed>  $data
     * @return list<string>
     */
    public static function keys(array $data): array
    {
        $keys = [];
        foreach (self::uniqueValues($data) as $field => $value) {
            $keys[] = hash('sha256', $field.':'.StudentImportColumns::fold($value));
        }
        if (filled($data['birth_date'] ?? null) && filled($data['last_name'] ?? null)) {
            $keys[] = hash('sha256', 'identity:'.StudentImportColumns::fold((string) $data['last_name']).'|'.StudentImportColumns::fold((string) ($data['first_names'] ?? '')).'|'.substr((string) $data['birth_date'], 0, 10));
        }

        return $keys;
    }

    /**
     * Values that identify one student only: a standard (12-digit) CIN, the phone and the email
     * (unique per the establishment file). A non-standard CIN may be shared and identifies nobody.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,string>
     */
    public static function uniqueValues(array $data): array
    {
        $values = [];
        foreach (self::UNIQUE_FIELDS as $field) {
            $value = $data[$field] ?? null;
            if (filled($value) && ($field !== 'cin' || StudentNormalization::isStandardCin((string) $value))) {
                $values[$field] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * Must be called inside the writing transaction.
     *
     * @param  list<string>  $keys
     */
    public static function acquire(array $keys): void
    {
        $keys = array_values(array_unique($keys));
        sort($keys);
        foreach ($keys as $key) {
            DB::table('student_identity_locks')->insertOrIgnore(['key' => $key]);
            DB::table('student_identity_locks')->where('key', $key)->lockForUpdate()->first();
        }
    }
}
