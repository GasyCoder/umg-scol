<?php

namespace App\Support\Scholarships;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Texts printed on the blank DDB labels, set in Paramètres › Plateforme (platform_settings).
 * An empty value falls back to its default; the link of the QR code defaults to the configuration.
 */
final class DdbLabel
{
    /** Setting key => default text. */
    public const TEXTS = [
        'ddb_label_heading' => 'Bourse nationale',
        'ddb_label_caption' => 'Numéro DDB',
        'ddb_label_hint' => 'Suivi du dossier en ligne',
        'ddb_label_footer' => 'Service Scolarité Centrale',
    ];

    public const URL_KEY = 'ddb_label_url';

    private const CACHE_KEY = 'platform.ddb_label';

    /** @return array<string,string> Stored values, empty when the default applies. */
    public static function raw(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('platform_settings')->whereIn('key', self::keys())->pluck('value', 'key')->all());

        return collect(self::keys())->mapWithKeys(fn (string $key) => [$key => is_string($stored[$key] ?? null) ? trim($stored[$key]) : ''])->all();
    }

    /** @return array{heading:string,caption:string,hint:string,footer:string,url:string} */
    public static function texts(): array
    {
        $raw = self::raw();
        $value = fn (string $key) => $raw[$key] !== '' ? $raw[$key] : self::defaults()[$key];

        return ['heading' => $value('ddb_label_heading'), 'caption' => $value('ddb_label_caption'), 'hint' => $value('ddb_label_hint'), 'footer' => $value('ddb_label_footer'), 'url' => $value(self::URL_KEY)];
    }

    /** @return array<string,string> */
    public static function defaults(): array
    {
        return [...self::TEXTS, self::URL_KEY => (string) config('scholarships.label_url')];
    }

    /** Standard QR code of the link, as an SVG data URI (same code on every label). */
    public static function qr(string $url): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode((new Writer(new ImageRenderer(new RendererStyle(240, 0), new SvgImageBackEnd)))->writeString($url));
    }

    /** Link without its scheme, printed under the QR code. */
    public static function urlLabel(string $url): string
    {
        return (string) preg_replace('#^https?://#', '', rtrim($url, '/'));
    }

    /** @param array<string,string|null> $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
        DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return [...array_keys(self::TEXTS), self::URL_KEY];
    }
}
