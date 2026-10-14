<?php

namespace App\Models;

use App\Enums\StudentRecordStatus;
use Database\Factories\StudentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @property StudentRecordStatus $status */
#[Fillable(['student_id', 'academic_year_id', 'establishment_id', 'level_id', 'program_id', 'status', 'declared_by', 'declared_at'])]
#[Hidden(['active_academic_year_id'])]
class StudentRecord extends Model
{
    /** @use HasFactory<StudentRecordFactory> */
    use HasFactory, LogsActivity;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['status' => StudentRecordStatus::class, 'declared_at' => 'datetime', 'student_id' => 'integer', 'establishment_id' => 'integer', 'academic_year_id' => 'integer', 'level_id' => 'integer', 'program_id' => 'integer', 'declared_by' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Une déclaration annuelle doit être désactivée, pas supprimée.');
        });
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by');
    }

    /**
     * @param  Builder<StudentRecord>  $query
     * @return Builder<StudentRecord>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when($user->hasRole('establishment'), fn ($query) => $query->where('establishment_id', $user->establishment_id));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('student_records')->logOnly(['student_id', 'academic_year_id', 'establishment_id', 'level_id', 'program_id', 'status', 'declared_by', 'declared_at'])->logOnlyDirty()->logEmptyChanges();
    }
}
