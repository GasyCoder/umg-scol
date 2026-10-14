<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class StudentIdentityLocks
{
    /**
     * @param  array<string,mixed>  $data
     * @return list<string>
     */
    public static function keys(array $data): array
    {
        $keys = [];
        foreach (['student_number', 'cin', 'bac_registration_number'] as $field) {
            if (filled($data[$field] ?? null)) {
                $keys[] = hash('sha256', $field.':'.StudentImportColumns::fold((string) $data[$field]));
            }
        }
        if (filled($data['birth_date'] ?? null) && filled($data['last_name'] ?? null)) {
            $keys[] = hash('sha256', 'identity:'.StudentImportColumns::fold((string) $data['last_name']).'|'.StudentImportColumns::fold((string) ($data['first_names'] ?? '')).'|'.substr((string) $data['birth_date'], 0, 10));
        }

        return $keys;
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
