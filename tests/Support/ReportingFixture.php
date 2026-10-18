<?php

namespace Tests\Support;

use App\Enums\StudentCorrectionField;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Program;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentImport;
use App\Models\StudentInformationConfirmation;
use App\Models\StudentLinkRequest;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentConfirmationContext;

/**
 * Small, fully known data set for dashboard and report figures (fictitious values only).
 *
 * Current year, establishment A: s1 (L1, Info, confirmed), s2 (L1, Info, stale confirmation), s3 (L2, never
 * confirmed), s4 (inactive declaration), s5 (active declaration, inactive identity).
 * Establishment B: b1, b2 (L1). Previous year: one extra declaration at A.
 */
final class ReportingFixture
{
    /** @var array<string,mixed> */
    public array $m = [];

    public static function build(): self
    {
        $f = new self;
        $f->m['previous'] = AcademicYear::factory()->create(['name' => '2025-2026', 'is_current' => false]);
        $f->m['year'] = AcademicYear::factory()->create(['name' => '2026-2027', 'is_current' => true, 'is_active' => true]);
        // A later, non-current year must never be picked as "the" year.
        $f->m['next'] = AcademicYear::factory()->create(['name' => '2027-2028', 'is_current' => false]);
        $f->m['a'] = Establishment::factory()->create(['name' => 'Faculté A']);
        $f->m['b'] = Establishment::factory()->create(['name' => 'Institut B']);
        $f->m['l1'] = Level::factory()->create(['name' => 'Licence 1', 'sort_order' => 1]);
        $f->m['l2'] = Level::factory()->create(['name' => 'Licence 2', 'sort_order' => 2]);
        $f->m['info'] = Program::factory()->create(['establishment_id' => $f->m['a']->id, 'name' => 'Informatique']);
        $declare = function (string $key, Establishment $establishment, Level $level, ?Program $program = null, string $status = 'active', bool $identity = true, ?AcademicYear $year = null) use ($f): Student {
            $student = Student::factory()->create(['is_active' => $identity, 'phone' => '0340000'.random_int(100, 999), 'email' => $key.'@example.test']);
            $f->m[$key.'_record'] = StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => ($year ?? $f->m['year'])->id, 'establishment_id' => $establishment->id, 'level_id' => $level->id, 'program_id' => $program?->id, 'status' => $status]);

            return $f->m[$key] = $student;
        };
        $declare('s1', $f->m['a'], $f->m['l1'], $f->m['info']);
        $declare('s2', $f->m['a'], $f->m['l1'], $f->m['info']);
        $declare('s3', $f->m['a'], $f->m['l2']);
        $declare('s4', $f->m['a'], $f->m['l1'], null, 'inactive');
        $declare('s5', $f->m['a'], $f->m['l1'], null, 'active', false);
        $declare('b1', $f->m['b'], $f->m['l1']);
        $declare('b2', $f->m['b'], $f->m['l1']);
        $declare('old', $f->m['a'], $f->m['l1'], null, 'active', true, $f->m['previous']);

        foreach (['superadmin', 'developer', 'central_scholarship_office'] as $role) {
            $f->m[$role] = User::factory()->create(['name' => ucfirst($role).' test'])->assignRole($role);
        }
        foreach (['a', 'b'] as $key) {
            $f->m['staff_'.$key] = User::factory()->create(['name' => 'Agent '.strtoupper($key)])->assignRole('establishment');
            $f->m['staff_'.$key]->forceFill(['establishment_id' => $f->m[$key]->id])->save();
        }

        // Confirmations: s1 current, s2 confirmed then changed (stale), s3 never.
        foreach (['s1', 's2'] as $key) {
            StudentInformationConfirmation::query()->create(['student_id' => $f->m[$key]->id, 'sequence' => 1, 'user_id' => $f->m['superadmin']->id, 'student_record_id' => $f->m[$key.'_record']->id, 'academic_year_id' => $f->m['year']->id, 'information_fingerprint' => (string) StudentConfirmationContext::load($f->m[$key]->fresh())->fingerprint(), 'confirmed_at' => now()]);
        }
        $f->m['s2']->update(['phone' => '0329998877']);

        // Corrections: pending for s1 (A) and b1 (B), approved for s2 (A).
        $correction = fn (Student $student, string $status) => StudentCorrectionRequest::query()->create(['student_id' => $student->id, 'user_id' => $f->m['superadmin']->id, 'field' => StudentCorrectionField::Address, 'proposed_value' => 'Adresse corrigée', 'current_fingerprint' => str_repeat('0', 64), 'status' => $status]);
        $correction($f->m['s1'], 'pending');
        $correction($f->m['b1'], 'pending');
        $correction($f->m['s2'], 'approved');

        // Link requests pending for a student of A and one of B.
        foreach (['s3', 'b2'] as $key) {
            $account = User::factory()->create()->assignRole('student');
            StudentLinkRequest::query()->create(['user_id' => $account->id, 'student_id' => $f->m[$key]->id, 'status' => 'pending', 'requested_at' => now()]);
        }

        // Imports of the current year: A completed with rejections, A failed, B completed.
        $import = fn (Establishment $establishment, string $status, int $total, int $valid, int $rejected, int $duplicate, int $declared) => StudentImport::query()->create([
            'establishment_id' => $establishment->id, 'academic_year_id' => $f->m['year']->id, 'user_id' => $f->m['superadmin']->id, 'original_filename' => 'liste.csv', 'stored_path' => 'imports/x.csv', 'disk' => 'local',
            'status' => $status, 'total_rows' => $total, 'valid_rows' => $valid, 'warning_rows' => 0, 'rejected_rows' => $rejected, 'duplicate_rows' => $duplicate, 'created_rows' => $declared, 'updated_rows' => 0, 'declared_rows' => $declared, 'skipped_rows' => $total - $declared,
        ]);
        $import($f->m['a'], 'completed', 10, 7, 2, 1, 7);
        $import($f->m['a'], 'failed', 5, 0, 0, 0, 0);
        $import($f->m['b'], 'completed', 4, 4, 0, 0, 4);

        // Paper files of the current academic year: s1 pending (today), s2 rejected, b1 validated.
        $sequence = 0;
        $file = function (string $key, string $status, $receivedAt) use ($f, &$sequence) {
            $sequence++;
            ScholarshipApplication::query()->create(['application_number' => 'BRS-T'.$sequence, 'academic_year_id' => $f->m['year']->id, 'sequence' => $sequence, 'student_id' => $f->m[$key]->id, 'student_record_id' => $f->m[$key.'_record']->id, 'status' => $status, 'received_by' => $f->m['central_scholarship_office']->id, 'received_at' => $receivedAt]);
        };
        $file('s1', 'pending', now());
        $file('s2', 'rejected', now()->subDays(2));
        $file('b1', 'validated', now()->subDays(3));

        return $f;
    }

    public function __get(string $key): mixed
    {
        return $this->m[$key];
    }
}
