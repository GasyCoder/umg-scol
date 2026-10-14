<?php

namespace App\Actions\Students;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetStudentStatus
{
    public function handle(User $actor, Student $student, bool $active): void
    {
        DB::transaction(function () use ($actor, $student, $active): void {
            $target = Student::query()->lockForUpdate()->findOrFail($student->id);
            Gate::forUser($actor)->authorize('deactivate', $target);
            $target->update(['is_active' => $active]);
        });
    }
}
