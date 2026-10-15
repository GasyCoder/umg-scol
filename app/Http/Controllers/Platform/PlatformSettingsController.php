<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\SavePlatformBranding;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SavePlatformBrandingRequest;
use App\Support\Branding;
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
        ]);
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
