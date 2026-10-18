<?php

namespace App\Support\Imports;

use App\Models\StudentImport;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Progress of a running analysis or confirmation, shown on the import page.
 * Kept outside the database: the work runs in one transaction, invisible to other requests until it ends.
 */
class ImportProgress
{
    /** waiting = queued, not started yet; prepare = locks and references before the rows. */
    public const PHASES = ['waiting', 'prepare', 'rows'];

    private static function store(): Repository
    {
        return Cache::store((string) config('student_imports.progress_store'));
    }

    private static function key(StudentImport $import): string
    {
        return 'student-imports:'.$import->id.':progress';
    }

    public static function put(StudentImport $import, string $phase, int $done, int $total): void
    {
        self::store()->put(self::key($import), ['phase' => $phase, 'done' => min($done, $total), 'total' => $total, 'at' => now()->getTimestamp()], now()->addHours(2));
    }

    /** @return array{phase:string, done:int, total:int, percent:int, idle:int}|null idle = seconds since the last update */
    public static function get(StudentImport $import): ?array
    {
        $value = self::store()->get(self::key($import));
        if (! is_array($value) || ! in_array($value['phase'] ?? null, self::PHASES, true)) {
            return null;
        }
        $done = (int) $value['done'];
        $total = max(0, (int) $value['total']);

        return ['phase' => (string) $value['phase'], 'done' => $done, 'total' => $total, 'percent' => $total === 0 ? 0 : (int) floor($done * 100 / $total),
            'idle' => max(0, now()->getTimestamp() - (int) ($value['at'] ?? now()->getTimestamp()))];
    }

    public static function forget(StudentImport $import): void
    {
        self::store()->forget(self::key($import));
    }
}
