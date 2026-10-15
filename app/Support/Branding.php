<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Platform identity shown on both portals: names, institution, contact, logo and favicon.
 * Stored in platform_settings, cached until the next save, with safe defaults when empty.
 */
final class Branding
{
    public const DISK = 'local';

    public const TEXT_KEYS = ['institution_name', 'scol_name', 'bourse_name', 'support_email'];

    public const FILE_KEYS = ['logo_path', 'favicon_path'];

    private const CACHE_KEY = 'platform.branding';

    /** @return array{institution_name:string,scol_name:string,bourse_name:string,support_email:string|null,logo_path:string|null,favicon_path:string|null} */
    public static function raw(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('platform_settings')->pluck('value', 'key')->all());

        return [
            'institution_name' => self::text($stored, 'institution_name') ?? 'Université de Mahajanga',
            'scol_name' => self::text($stored, 'scol_name') ?? (string) config('app.name'),
            'bourse_name' => self::text($stored, 'bourse_name') ?? (string) config('app.name'),
            'support_email' => self::text($stored, 'support_email'),
            'logo_path' => self::text($stored, 'logo_path'),
            'favicon_path' => self::text($stored, 'favicon_path'),
        ];
    }

    /**
     * Public view of the identity: file paths become versioned URLs (relative, valid on both portals).
     *
     * @return array{institution_name:string,scol_name:string,bourse_name:string,support_email:string|null,logo_url:string|null,favicon_url:string|null}
     */
    public static function get(): array
    {
        $raw = self::raw();

        return [
            'institution_name' => $raw['institution_name'], 'scol_name' => $raw['scol_name'], 'bourse_name' => $raw['bourse_name'], 'support_email' => $raw['support_email'],
            'logo_url' => self::url('logo', $raw['logo_path']), 'favicon_url' => self::url('favicon', $raw['favicon_path']),
        ];
    }

    /** @param array<string,string|null> $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
        DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
    }

    private static function url(string $asset, ?string $path): ?string
    {
        return $path === null ? null : '/branding/'.$asset.'?v='.substr(md5($path), 0, 10);
    }

    /** @param array<string,mixed> $stored */
    private static function text(array $stored, string $key): ?string
    {
        $value = $stored[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
