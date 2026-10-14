<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PortalEmailLock
{
    public static function key(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    /** @param list<string> $values */
    public static function acquire(array $values): void
    {
        $keys = array_map(self::key(...), $values);
        sort($keys);
        foreach (array_unique($keys) as $key) {
            DB::table('portal_email_locks')->insertOrIgnore(['key' => $key]);
            DB::table('portal_email_locks')->where('key', $key)->lockForUpdate()->first();
        }
    }
}
