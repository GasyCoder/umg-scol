<?php

use App\Enums\SituationVerification;
use App\Enums\StudentRecordStatus;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\StudentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->record = StudentRecord::factory()->create(['source' => 'central', 'status' => 'pending', 'verification' => 'matched']);
    $this->import = StudentImport::query()->create([
        'kind' => 'establishment', 'establishment_id' => $this->record->establishment_id,
        'academic_year_id' => $this->record->academic_year_id, 'user_id' => $this->record->declared_by,
        'original_filename' => 'liste.xlsx', 'stored_path' => 'unused.xlsx',
        'status' => 'completed', 'committed_at' => now(),
    ]);
    $this->record->update(['matched_import_id' => $this->import->id]);
    $this->row = StudentImportRow::query()->create([
        'student_import_id' => $this->import->id, 'row_number' => 2, 'raw_data' => [],
        'status' => 'valid', 'resolution' => 'matched', 'matched_student_id' => $this->record->student_id,
        'matched_student_record_id' => $this->record->id,
    ]);
    $this->migration = require database_path('migrations/2026_10_18_140000_verify_confirmed_establishment_matches.php');
});

it('repairs a historical match once with a system audit and preserves its identity and non-scholarship origin', function () {
    $identity = $this->record->student->toArray();
    $this->migration->up();
    expect($this->record->refresh()->verification)->toBe(SituationVerification::Verified)
        ->and($this->record->status)->toBe(StudentRecordStatus::Active)
        ->and($this->record->source->value)->toBe('central')->and($this->record->is_scholarship_holder)->toBeFalse()
        ->and($this->record->student->toArray())->toBe($identity);
    $log = Activity::query()->where('event', 'verified_by_import')->sole();
    expect($log->causer_id)->toBeNull()->and($log->subject_id)->toBe($this->record->id)
        ->and($log->properties->toArray())->toBe(['import_id' => $this->import->id, 'changed_fields' => ['verification', 'status']]);
    $this->migration->up();
    $this->migration->down();
    expect(Activity::query()->where('event', 'verified_by_import')->count())->toBe(1)
        ->and($this->record->refresh()->status)->toBe(StudentRecordStatus::Active);
});

it('preserves an administrative deactivation when verifying a historical match', function () {
    $this->record->update(['status' => 'inactive']);
    $this->migration->up();
    expect($this->record->refresh()->verification)->toBe(SituationVerification::Verified)
        ->and($this->record->status)->toBe(StudentRecordStatus::Inactive);
});

it('requires proof of a completed establishment import before repairing a historical match', function (string $invalid) {
    match ($invalid) {
        'preview only' => $this->import->update(['status' => 'ready']),
        'missing confirmation date' => $this->import->update(['committed_at' => null]),
        'different import kind' => $this->import->update(['kind' => 'preinscription']),
        'different academic year' => $this->import->update(['academic_year_id' => AcademicYear::factory()->create()->id]),
        'different establishment' => $this->import->update(['establishment_id' => Establishment::factory()->create()->id]),
        'invalid row' => $this->row->update(['status' => 'warning']),
        'skipped row' => $this->row->update(['resolution' => 'skipped']),
        'missing record link' => $this->row->update(['matched_student_record_id' => null]),
        'different student' => $this->row->update(['matched_student_id' => Student::factory()->create()->id]),
        'inactive identity' => $this->record->student->update(['is_active' => false]),
        'no match yet' => $this->record->update(['verification' => 'pending']),
        'different origin' => $this->record->update(['source' => 'preinscription']),
        'another active situation' => StudentRecord::factory()->create(['student_id' => $this->record->student_id, 'academic_year_id' => $this->record->academic_year_id]),
    };
    $this->migration->up();
    expect($this->record->refresh()->status)->toBe(StudentRecordStatus::Pending)
        ->and(Activity::query()->where('event', 'verified_by_import')->count())->toBe(0);
})->with([
    'preview only', 'missing confirmation date', 'different import kind', 'different academic year', 'different establishment',
    'invalid row', 'skipped row', 'missing record link', 'different student', 'inactive identity', 'no match yet', 'different origin', 'another active situation',
]);

it('accepts a confirmed multi-establishment import only when its row points to the same situation', function () {
    $this->import->update(['establishment_id' => null]);
    $this->migration->up();
    expect($this->record->refresh()->verification)->toBe(SituationVerification::Verified)
        ->and($this->record->status)->toBe(StudentRecordStatus::Active);
});

it('rolls back the correction if its audit cannot be written', function () {
    Activity::creating(function (Activity $activity) {
        if ($activity->event === 'verified_by_import') {
            throw new RuntimeException('Audit unavailable');
        }
    });
    try {
        expect(fn () => $this->migration->up())->toThrow(RuntimeException::class, 'Audit unavailable');
    } finally {
        Activity::flushEventListeners();
    }
    expect($this->record->refresh()->verification)->toBe(SituationVerification::Matched)
        ->and($this->record->status)->toBe(StudentRecordStatus::Pending)
        ->and(Activity::query()->where('event', 'verified_by_import')->count())->toBe(0);
});
