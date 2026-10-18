<?php

namespace App\Support;

use App\Support\Scholarships\DdbLabel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Emails sent to the official address of a student file, each one switched on or off in
 * Paramètres › Plateforme (platform_settings, on by default). Nothing is sent without an email.
 */
final class StudentMails
{
    /** Setting key => label of the settings screen. */
    public const KINDS = [
        'mail_student_registered' => 'Fiche étudiante créée',
        'mail_scholarship_received' => 'Dossier de bourse reçu',
    ];

    private const CACHE_KEY = 'platform.student_mails';

    /** @return array<string,bool> */
    public static function settings(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('platform_settings')->whereIn('key', array_keys(self::KINDS))->pluck('value', 'key')->all());

        return collect(self::KINDS)->mapWithKeys(fn ($label, string $key) => [$key => ($stored[$key] ?? '1') !== '0'])->all();
    }

    public static function enabled(string $key): bool
    {
        return self::settings()[$key] ?? false;
    }

    /** @param array<string,bool> $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => $value ? '1' : '0', 'updated_at' => now(), 'created_at' => now()]);
        }
        DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Public link of the student portal, the one printed on the DDB labels. */
    public static function portalUrl(): string
    {
        return DdbLabel::texts()['url'];
    }

    /**
     * Shared frame of the student emails, built with tables and inline styles so that it holds in
     * mail clients: hidden preview text, institution band, white card, portal button, footer.
     */
    public static function html(string $body, string $preview = '', bool $portalButton = true, string $subtitle = 'Bourses nationales · Scolarité Centrale'): string
    {
        $url = self::portalUrl();
        $institution = e(Branding::get()['institution_name']);

        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            .'<body style="margin:0;padding:0;background:#f1f5f9">'
            .'<div style="display:none;max-height:0;overflow:hidden;opacity:0">'.e($preview).'</div>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
            .'<tr><td align="center">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px">'
            // Institution band.
            .'<tr><td style="background:#1e3a8a;border-radius:12px 12px 0 0;padding:18px 28px">'
            .'<p style="margin:0;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#ffffff">'.$institution.'</p>'
            .'<p style="margin:2px 0 0;font-size:12px;color:#c7d2fe">'.e($subtitle).'</p>'
            .'</td></tr>'
            // Message.
            .'<tr><td style="background:#ffffff;padding:28px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 12px 12px;font-size:15px;line-height:1.55">'
            .$body
            .($portalButton ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 4px"><tr><td style="border-radius:8px;background:#1d4ed8">'
            .'<a href="'.e($url).'" style="display:inline-block;padding:12px 22px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px">Ouvrir le portail étudiant</a>'
            .'</td></tr></table>'
            .'<p style="margin:8px 0 0;font-size:13px;color:#64748b">ou rendez-vous sur <a href="'.e($url).'" style="color:#1d4ed8;text-decoration:none">'.e(DdbLabel::urlLabel($url)).'</a></p>' : '')
            .'</td></tr>'
            // Footer.
            .'<tr><td style="padding:16px 28px;font-size:12px;line-height:1.5;color:#64748b;text-align:center">'
            .'Message automatique, merci de ne pas y répondre.<br>Pour toute question, adressez-vous à la Scolarité Centrale ou à votre établissement.'
            .'</td></tr>'
            .'</table></td></tr></table></body></html>';
    }

    /** A highlighted reference number, as shown in the emails. */
    public static function reference(string $label, string $value): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0"><tr>'
            .'<td style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px;padding:16px 20px;text-align:center">'
            .'<p style="margin:0;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#4338ca">'.e($label).'</p>'
            .'<p style="margin:6px 0 0;font-size:24px;font-weight:700;letter-spacing:.04em;font-family:Consolas,Menlo,monospace;color:#0f172a">'.e($value).'</p>'
            .'</td></tr></table>';
    }

    /** @param array<string,string> $rows label => value */
    public static function details(array $rows): string
    {
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border-top:1px solid #e2e8f0">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;color:#64748b;width:42%">'.e($label).'</td>'
                .'<td style="padding:10px 0;border-bottom:1px solid #e2e8f0;font-size:14px;font-weight:600;color:#0f172a">'.e($value).'</td></tr>';
        }

        return $html.'</table>';
    }
}
