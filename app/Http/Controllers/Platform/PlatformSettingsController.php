<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\SaveDdbLabelTexts;
use App\Actions\Platform\SaveDdbNumbering;
use App\Actions\Platform\SavePlatformBranding;
use App\Actions\Platform\SaveStudentMails;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SaveDdbLabelTextsRequest;
use App\Http\Requests\Platform\SaveDdbNumberingRequest;
use App\Http\Requests\Platform\SavePlatformBrandingRequest;
use App\Http\Requests\Platform\SaveStudentMailsRequest;
use App\Support\Branding;
use App\Support\Scholarships\DdbLabel;
use App\Support\Scholarships\DdbNumber;
use App\Support\StudentMails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformSettingsController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('branding.manage-ui');
        $stored = DB::table('platform_settings')->pluck('value', 'key');

        return Inertia::render('settings/platform', [
            // Raw values (empty when the default applies) and the defaults shown as placeholders.
            'values' => collect(Branding::TEXT_KEYS)->mapWithKeys(fn ($key) => [$key => $stored[$key] ?? ''])->all(),
            'defaults' => ['institution_name' => 'Université de Mahajanga', 'scol_name' => config('app.name'), 'bourse_name' => config('app.name')],
            // Numbering of the paper files received (DDB), generated at reception.
            'ddb' => [...DdbNumber::settings(), 'defaultPattern' => DdbNumber::DEFAULT_PATTERN, 'defaultDigits' => DdbNumber::DEFAULT_DIGITS, 'tokens' => DdbNumber::TOKENS],
            // Texts of the blank DDB labels (empty when the default applies) and the current QR code, for the preview.
            'labels' => ['values' => DdbLabel::raw(), 'defaults' => DdbLabel::defaults(), 'qr' => DdbLabel::qr(DdbLabel::texts()['url'])],
            // Emails sent to the official address of a student file.
            'studentMails' => ['values' => StudentMails::settings(), 'labels' => StudentMails::KINDS],
        ]);
    }

    public function updateDdb(SaveDdbNumberingRequest $request, SaveDdbNumbering $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated('ddb_pattern'), $request->integer('ddb_digits'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Numérotation des dossiers DDB enregistrée.']);

        return to_route('platform.edit');
    }

    public function updateLabels(SaveDdbLabelTextsRequest $request, SaveDdbLabelTexts $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Textes des étiquettes DDB enregistrés.']);

        return to_route('platform.edit');
    }

    public function updateStudentMails(SaveStudentMailsRequest $request, SaveStudentMails $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Emails aux étudiants enregistrés.']);

        return to_route('platform.edit');
    }

    public function update(SavePlatformBrandingRequest $request, SavePlatformBranding $action): RedirectResponse
    {
        $action->handle(
            $request->user(),
            $request->safe()->only(Branding::TEXT_KEYS),
            ['logo' => $request->file('logo'), 'favicon' => $request->file('favicon')],
            ['logo' => $request->boolean('remove_logo'), 'favicon' => $request->boolean('remove_favicon')],
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identité de la plateforme enregistrée.']);

        return to_route('platform.edit');
    }

    /** Public on both portals (login screens and favicons are shown before sign-in). */
    public function asset(string $asset): StreamedResponse
    {
        $path = Branding::raw()[$asset.'_path'];
        $disk = Storage::disk(Branding::DISK);
        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=604800', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'"]);
    }
}
