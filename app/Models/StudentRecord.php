<?php

namespace App\Models;

use App\Enums\RepeatCode;
use App\Enums\SituationSource;
use App\Enums\SituationVerification;
use App\Enums\StudentRecordStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\Trashable;
use Carbon\CarbonInterface;
use Database\Factories\StudentRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property StudentRecordStatus $status
 * @property RepeatCode|null $repeat_code
 * @property CarbonInterface|null $scholarship_requested_at
 * @property bool $inactive_with_identity
 * @property SituationSource $source
 * @property SituationVerification $verification
 * @property bool $is_scholarship_holder
 */
#[Fillable(['student_id', 'academic_year_id', 'establishment_id', 'level_id', 'program_id', 'repeat_code', 'scholarship_requested_at', 'scholarship_request_reference', 'status', 'inactive_with_identity', 'source', 'verification', 'is_scholarship_holder', 'preinscription_reference', 'matched_import_id', 'declared_by', 'declared_at'])]
#[Hidden(['active_academic_year_id', 'live_marker'])]
class StudentRecord extends Model
{
    /** @use HasFactory<StudentRecordFactory> */
    use HasFactory, HasPublicUuid, LogsActivity, Trashable;

    protected $attributes = ['status' => 'active', 'source' => 'establishment', 'verification' => 'verified', 'is_scholarship_holder' => false];

    protected function casts(): array
    {
        return ['status' => StudentRecordStatus::class, 'repeat_code' => RepeatCode::class, 'scholarship_requested_at' => 'date', 'inactive_with_identity' => 'boolean', 'source' => SituationSource::class, 'verification' => SituationVerification::class, 'is_scholarship_holder' => 'boolean', 'matched_import_id' => 'integer', 'declared_at' => 'datetime', 'student_id' => 'integer', 'establishment_id' => 'integer', 'academic_year_id' => 'integer', 'level_id' => 'integer', 'program_id' => 'integer', 'declared_by' => 'integer'];
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
    public function scopeCurrentActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereHas('academicYear', fn ($year) => $year->where('is_current', true)->where('is_active', true));
    }

    /**
     * @param  Builder<StudentRecord>  $query
     * @return Builder<StudentRecord>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Qualified: callers join other tables that also carry an establishment_id (programs, mentions).
        return $query->when($user->hasRole('establishment'), fn ($query) => $query->where($query->qualifyColumn('establishment_id'), $user->establishment_id));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('student_records')->logOnly(['student_id', 'academic_year_id', 'establishment_id', 'level_id', 'program_id', 'repeat_code', 'scholarship_requested_at', 'scholarship_request_reference', 'status', 'source', 'verification', 'is_scholarship_holder', 'declared_by', 'declared_at'])->logOnlyDirty()->logEmptyChanges();
    }
}
