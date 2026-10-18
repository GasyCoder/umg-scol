<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email asking a student to complete or check their record: who sent it, when, which fields.
 * Never the values of those fields.
 *
 * @property list<string> $fields
 * @property CarbonInterface $created_at
 */
#[Fillable(['student_id', 'sent_by', 'scholarship_application_id', 'fields', 'message'])]
class StudentInformationRequest extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['fields' => 'array', 'student_id' => 'integer', 'sent_by' => 'integer', 'scholarship_application_id' => 'integer'];
    }

    /** @return BelongsTo<Student,$this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User,$this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
