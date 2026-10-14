<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's declaration that the information shown at that time was correct.
 * Holds identifiers and a keyed fingerprint only; whether it is still current is derived, never stored.
 *
 * @property CarbonInterface $confirmed_at
 */
#[Fillable(['student_id', 'sequence', 'user_id', 'student_record_id', 'academic_year_id', 'information_fingerprint', 'confirmed_at'])]
#[Hidden(['information_fingerprint'])]
class StudentInformationConfirmation extends Model
{
    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'student_id' => 'integer', 'sequence' => 'integer', 'user_id' => 'integer', 'student_record_id' => 'integer', 'academic_year_id' => 'integer'];
    }

    /** @return BelongsTo<Student,$this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AcademicYear,$this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
