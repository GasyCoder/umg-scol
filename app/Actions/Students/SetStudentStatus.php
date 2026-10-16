<?php

namespace App\Actions\Students;

use App\Enums\StudentRecordStatus;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Deactivating a student also deactivates its active declarations of the current academic year
 * (marked inactive_with_identity); reactivating restores those declarations only. Past years
 * stay untouched: they are history.
 */
class SetStudentStatus
{
    public function handle(User $actor, Student $student, bool $active): void
    {
        DB::transaction(function () use ($actor, $student, $active): void {
            $target = Student::query()->lockForUpdate()->findOrFail($student->id);
            Gate::forUser($actor)->authorize('deactivate', $target);
            $target->update(['is_active' => $active]);
            $records = StudentRecord::query()->where('student_id', $target->id)->lockForUpdate()
                ->when(! $active, fn ($q) => $q->where('status', StudentRecordStatus::Active)->currentActive())
                ->when($active, fn ($q) => $q->where('inactive_with_identity', true))
                ->orderBy('id')->get();
            foreach ($records as $record) {
                if ($active && StudentRecord::query()->where('student_id', $target->id)->where('academic_year_id', $record->academic_year_id)->where('status', StudentRecordStatus::Active)->exists()) {
                    // Another declaration of that year became active meanwhile: keep this one inactive.
                    $record->update(['inactive_with_identity' => false]);

                    continue;
                }
                $record->update(['status' => $active ? StudentRecordStatus::Active : StudentRecordStatus::Inactive, 'inactive_with_identity' => ! $active]);
            }
        });
    }
}
