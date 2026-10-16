<?php

namespace App\Support;

use App\Enums\StudentCorrectionStatus;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentInformationConfirmation;
use App\Models\StudentRecord;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Current situation of a student for confirmation purposes, resolved server-side only:
 * current active academic year, then the student's single active declaration for it.
 * The confirmation state (never_confirmed, confirmed, stale) is derived from the fingerprint, never stored.
 */
final class StudentConfirmationContext
{
    public const NEVER_CONFIRMED = 'never_confirmed';

    public const CONFIRMED = 'confirmed';

    public const STALE = 'stale';

    private const MESSAGES = [
        'inactive_student' => 'La fiche étudiante est inactive : la confirmation n’est pas disponible.',
        'no_current_year' => 'Aucune année universitaire courante n’est ouverte : la confirmation n’est pas disponible pour le moment.',
        'no_current_record' => 'Aucune situation de votre établissement pour l’année universitaire courante : contactez votre établissement.',
        'inactive_record' => 'Votre situation pour l’année universitaire courante est inactive : contactez votre établissement.',
        'ambiguous' => 'Votre situation pour l’année universitaire courante doit être régularisée par votre établissement avant confirmation.',
        'pending_corrections' => 'Une ou plusieurs corrections sont encore en attente. Vous pourrez confirmer vos informations après leur traitement ou leur annulation.',
    ];

    private ?string $fingerprint = null;

    private function __construct(
        public readonly Student $student,
        public readonly ?StudentRecord $record,
        public readonly ?string $blocker,
        public readonly ?StudentInformationConfirmation $latest,
    ) {}

    /**
     * Inside the confirmation transaction ($lock), every read is a locking read so that the
     * computed version is the one held under lock. Otherwise, records already loaded on the
     * student (with the record relations) are reused, so that the state matches what was displayed.
     */
    public static function load(Student $student, bool $lock = false): self
    {
        $latest = $student->informationConfirmations()->with('academicYear:id,name')->orderByDesc('sequence')->when($lock, fn ($q) => $q->lockForUpdate())->first();
        $years = AcademicYear::query()->where('is_current', true)->where('is_active', true)->when($lock, fn ($q) => $q->sharedLock())->get(['id']);
        $record = null;
        $blocker = $student->is_active ? null : 'inactive_student';
        if ($years->count() !== 1) {
            $blocker ??= $years->isEmpty() ? 'no_current_year' : 'ambiguous';
        } else {
            $yearId = $years->first()->id;
            $records = ! $lock && $student->relationLoaded('studentRecords')
                ? $student->studentRecords->where('academic_year_id', $yearId)->values()
                : StudentRecord::query()->with(self::recordRelations($lock))->where('student_id', $student->id)->where('academic_year_id', $yearId)->when($lock, fn ($q) => $q->lockForUpdate())->get();
            $active = $records->filter(fn (StudentRecord $candidate) => $candidate->status->value === 'active')->values();
            if ($records->isEmpty()) {
                $blocker ??= 'no_current_record';
            } elseif ($active->isEmpty()) {
                $blocker ??= 'inactive_record';
            } elseif ($active->count() > 1) {
                $blocker ??= 'ambiguous';
            } else {
                $record = $active->first();
            }
        }
        $pending = $blocker === null ? StudentCorrectionRequest::query()->where('student_id', $student->id)->where('status', StudentCorrectionStatus::Pending->value)->when($lock, fn ($q) => $q->sharedLock())->value('id') : null;
        if ($pending !== null) {
            $blocker = 'pending_corrections';
        }

        return new self($student, $record, $blocker, $latest);
    }

    /**
     * Relations the fingerprint reads, shared-locked when confirming.
     *
     * @return array<string,Closure(Relation<*,*,*>): mixed>
     */
    public static function recordRelations(bool $lock = false): array
    {
        $columns = ['academicYear' => ['id', 'name', 'is_current', 'is_active'], 'establishment' => ['id', 'name'], 'level' => ['id', 'name'], 'program' => ['id', 'name', 'mention_id'], 'program.mention' => ['id', 'name']];

        return array_map(fn (array $select) => fn (Relation $query) => $query->select($select)->when($lock, fn ($q) => $q->sharedLock()), $columns);
    }

    public function fingerprint(): ?string
    {
        return $this->record === null ? null : ($this->fingerprint ??= StudentInformationFingerprint::compute($this->student, $this->record));
    }

    /** Only the latest confirmation counts, and only if it matches the current situation. */
    public function status(): string
    {
        if ($this->latest === null) {
            return self::NEVER_CONFIRMED;
        }

        return $this->record !== null && self::matches($this->latest, $this->record, (string) $this->fingerprint()) ? self::CONFIRMED : self::STALE;
    }

    /** Single rule for "still current", shared with batch counts: same declaration and same fingerprint. */
    public static function matches(StudentInformationConfirmation $latest, StudentRecord $record, string $fingerprint): bool
    {
        return $latest->student_record_id === $record->id && hash_equals($latest->information_fingerprint, $fingerprint);
    }

    public function canConfirm(): bool
    {
        return $this->blocker === null && $this->record !== null;
    }

    public function message(): ?string
    {
        return $this->blocker === null ? null : self::MESSAGES[$this->blocker];
    }

    /**
     * Student portal payload: no fingerprint, no internal ID.
     *
     * @return array<string,mixed>
     */
    public function forStudent(): array
    {
        $fingerprint = $this->fingerprint();

        return [
            'status' => $this->status(), 'confirmed_at' => $this->latest?->confirmed_at->toIso8601String(),
            'academic_year' => $this->record?->academicYear->name, 'can_confirm' => $this->canConfirm(),
            'reason' => $this->blocker, 'message' => $this->message(),
            'version' => $this->canConfirm() && $fingerprint !== null ? StudentInformationFingerprint::viewToken($fingerprint) : null,
        ];
    }

    /**
     * Internal student page: state, date and year only.
     *
     * @return array<string,mixed>
     */
    public function forStaff(): array
    {
        return ['status' => $this->status(), 'confirmed_at' => $this->latest?->confirmed_at->toIso8601String(), 'academic_year' => $this->latest?->academicYear->name];
    }
}
