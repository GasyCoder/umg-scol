<?php

namespace App\Actions\References;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAcademicYear
{
    /** @param array<string, mixed> $data */
    public function handle(AcademicYear $year, array $data): AcademicYear
    {
        return DB::transaction(function () use ($year, $data): AcademicYear {
            $years = AcademicYear::query()->orderBy('id')->lockForUpdate()->get();
            $target = $year->exists ? $years->firstWhere('id', $year->id) : $year;
            abort_if($target === null, 404);
            $target->fill($data);
            if ($target->starts_at !== null && $target->ends_at !== null && $target->starts_at->greaterThan($target->ends_at)) {
                throw ValidationException::withMessages(['ends_at' => 'La date de fin doit être égale ou postérieure à la date de début.']);
            }
            if (! $target->is_active && ($data['is_current'] ?? false)) {
                throw ValidationException::withMessages(['is_current' => 'Activez cette année avant de la définir comme courante.']);
            }
            if (! $target->is_active) {
                $target->is_current = false;
            }
            if ($target->is_current) {
                foreach ($years as $previous) {
                    if ($previous->id !== $target->id && $previous->is_current) {
                        $previous->update(['is_current' => false]);
                    }
                }
            }
            $target->save();

            return $target;
        }, 5);
    }

    public function setCurrent(AcademicYear $year): AcademicYear
    {
        return $this->handle($year, ['is_current' => true]);
    }
}
