<?php

namespace App\Support\Scholarships;

use App\Models\AcademicYear;
use App\Models\Establishment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DDB number of a paper file, generated at reception from the pattern set by the platform
 * administrator (platform_settings). The {NUM} part is the reception sequence of the academic
 * year, which keeps every generated number unique within its year.
 */
final class DdbNumber
{
    public const DEFAULT_PATTERN = 'DDB-{ANNEE}-{NUM}';

    public const DEFAULT_DIGITS = 6;

    /** Tokens of the pattern, with their meaning for the settings screen. */
    public const TOKENS = [
        '{NUM}' => 'Numéro d’ordre dans l’année universitaire (obligatoire)',
        '{ANNEE}' => 'Première année de l’année universitaire (ex. 2026)',
        '{AU}' => 'Année universitaire (ex. 2026-2027)',
        '{ETAB}' => 'Code de l’établissement de l’étudiant',
    ];

    private const CACHE_KEY = 'platform.ddb';

    /** Blank printed on a label in place of the establishment code. */
    public const LABEL_BLANK = '____';

    /** @return array{pattern:string,digits:int} */
    public static function settings(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('platform_settings')->whereIn('key', ['ddb_pattern', 'ddb_digits'])->pluck('value', 'key')->all());
        $digits = (int) ($stored['ddb_digits'] ?? 0);

        return [
            'pattern' => is_string($stored['ddb_pattern'] ?? null) && $stored['ddb_pattern'] !== '' ? $stored['ddb_pattern'] : self::DEFAULT_PATTERN,
            'digits' => $digits >= 1 && $digits <= 10 ? $digits : self::DEFAULT_DIGITS,
        ];
    }

    /** @param array{pattern:?string,digits:?int} $values */
    public static function put(array $values): void
    {
        foreach (['ddb_pattern' => $values['pattern'], 'ddb_digits' => $values['digits'] === null ? null : (string) $values['digits']] as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
        DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Pre-printed part of a blank DDB label: the pattern around {NUM}, resolved for the academic year. The agent
     * writes the digits by hand; an establishment code, unknown in advance, is left as a blank to fill.
     *
     * @return array{prefix:string,suffix:string,digits:int}
     */
    public static function labelParts(AcademicYear $year): array
    {
        $parts = self::parts($year, self::LABEL_BLANK, 0);

        return ['prefix' => $parts['prefix'], 'suffix' => $parts['suffix'], 'digits' => $parts['digits']];
    }

    /**
     * Number split as printed on a label: the part before {NUM}, the number written by hand, the part after.
     *
     * @return array{prefix:string,number:string,suffix:string,digits:int}
     */
    public static function parts(AcademicYear $year, ?string $establishmentCode, int $sequence): array
    {
        $settings = self::settings();
        [$before, $after] = explode('{NUM}', $settings['pattern'], 2) + [1 => ''];
        $resolve = fn (string $part) => self::format($part, $settings['digits'], $year->name, $establishmentCode, $sequence);

        return ['prefix' => $resolve($before), 'number' => $resolve('{NUM}'), 'suffix' => $resolve($after), 'digits' => $settings['digits']];
    }

    public static function for(AcademicYear $year, ?Establishment $establishment, int $sequence): string
    {
        $settings = self::settings();

        return self::format($settings['pattern'], $settings['digits'], $year->name, $establishment?->code, $sequence);
    }

    public static function format(string $pattern, int $digits, string $academicYear, ?string $establishmentCode, int $sequence): string
    {
        return strtr($pattern, [
            '{NUM}' => str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT),
            '{ANNEE}' => preg_match('/\d{4}/', $academicYear, $match) === 1 ? $match[0] : $academicYear,
            '{AU}' => $academicYear,
            '{ETAB}' => mb_strtoupper((string) $establishmentCode),
        ]);
    }
}
