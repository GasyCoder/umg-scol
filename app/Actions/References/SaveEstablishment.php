<?php

namespace App\Actions\References;

use App\Models\Establishment;
use App\Support\Trash\FileDeletionOutbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveEstablishment
{
    public function __construct(private FileDeletionOutbox $outbox) {}

    /**
     * Saves the establishment and its optional logo in one transaction. A new logo is written
     * first and removed again if the transaction fails; a replaced or removed logo is deleted
     * only after commit, through the retrying file outbox.
     *
     * @param  array<string,mixed>  $attributes
     */
    public function handle(?Establishment $establishment, array $attributes, ?UploadedFile $logo, bool $removeLogo): Establishment
    {
        $newPath = null;
        if ($logo !== null) {
            $stored = $logo->storeAs('establishment-logos', Str::uuid7().'.'.$logo->extension(), Establishment::LOGO_DISK);
            if ($stored === false) {
                throw ValidationException::withMessages(['logo' => 'Le logo n’a pas pu être enregistré. Réessayez.']);
            }
            $newPath = $stored;
        }
        try {
            return DB::transaction(function () use ($establishment, $attributes, $newPath, $removeLogo) {
                $establishment ??= new Establishment;
                $previous = $establishment->logo_path;
                $establishment->fill($attributes);
                if ($newPath !== null) {
                    $establishment->logo_path = $newPath;
                } elseif ($removeLogo) {
                    $establishment->logo_path = null;
                }
                $establishment->save();
                if ($previous !== null && $previous !== $establishment->logo_path) {
                    $this->outbox->schedule(Establishment::LOGO_DISK, $previous);
                }

                return $establishment;
            });
        } catch (Throwable $error) {
            if ($newPath !== null) {
                Storage::disk(Establishment::LOGO_DISK)->delete($newPath);
            }
            throw $error;
        }
    }
}
