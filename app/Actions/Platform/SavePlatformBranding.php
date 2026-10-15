<?php

namespace App\Actions\Platform;

use App\Models\User;
use App\Support\Branding;
use App\Support\Trash\FileDeletionOutbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SavePlatformBranding
{
    public function __construct(private FileDeletionOutbox $outbox) {}

    /**
     * New files are written before the transaction (removed again on failure); replaced or
     * removed files are deleted after commit through the retrying outbox.
     *
     * @param  array<string,string|null>  $texts
     * @param  array{logo:?UploadedFile,favicon:?UploadedFile}  $files
     * @param  array{logo:bool,favicon:bool}  $remove
     */
    public function handle(User $actor, array $texts, array $files, array $remove): void
    {
        Gate::forUser($actor)->authorize('branding.manage-ui');
        $written = [];
        try {
            foreach ($files as $name => $file) {
                if ($file !== null) {
                    $path = $file->storeAs('branding', $name.'-'.Str::uuid7().'.'.$file->extension(), Branding::DISK);
                    if ($path === false) {
                        throw ValidationException::withMessages([$name => 'Le fichier n’a pas pu être enregistré. Réessayez.']);
                    }
                    $written[$name.'_path'] = $path;
                }
            }
            DB::transaction(function () use ($actor, $texts, $remove, $written) {
                $before = Branding::raw();
                $values = [];
                foreach (Branding::TEXT_KEYS as $key) {
                    $values[$key] = $texts[$key] ?? null;
                }
                foreach (['logo', 'favicon'] as $name) {
                    $key = $name.'_path';
                    $values[$key] = $written[$key] ?? ($remove[$name] ? null : $before[$key]);
                    if ($before[$key] !== null && $before[$key] !== $values[$key]) {
                        $this->outbox->schedule(Branding::DISK, $before[$key]);
                    }
                }
                $changed = array_keys(array_filter($values, fn ($value, $key) => $value !== (in_array($key, Branding::FILE_KEYS, true) ? $before[$key] : self::stored($key)), ARRAY_FILTER_USE_BOTH));
                Branding::put($values);
                if ($changed !== []) {
                    activity('platform')->causedBy($actor)->event('branding_updated')->withProperties(['changed_fields' => $changed])->log('branding_updated');
                }
            });
        } catch (Throwable $error) {
            Storage::disk(Branding::DISK)->delete(array_values($written));
            throw $error;
        }
    }

    /** Stored text value (not the displayed default), to report real changes only. */
    private static function stored(string $key): ?string
    {
        $value = DB::table('platform_settings')->where('key', $key)->value('value');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
