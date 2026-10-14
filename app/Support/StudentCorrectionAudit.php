<?php

namespace App\Support;

use App\Models\StudentCorrectionRequest;
use App\Models\User;

class StudentCorrectionAudit
{
    /** Field name and IDs only: never the old or proposed value. */
    public static function log(User $actor, StudentCorrectionRequest $correction, string $event): void
    {
        activity('student_corrections')->causedBy($actor)->performedOn($correction)->event($event)
            ->withProperties(['field' => $correction->field->value, 'student_id' => $correction->student_id, 'correction_id' => $correction->id])->log($event);
    }
}
