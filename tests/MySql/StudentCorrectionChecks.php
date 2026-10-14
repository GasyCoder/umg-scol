<?php

use App\Actions\Corrections\RequestStudentCorrection;
use App\Actions\Corrections\ReviewStudentCorrection;
use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Commits real rows to exercise row locks across two connections: run only on a disposable, migrated database
// whose name contains "check", e.g. DB_DATABASE=umg_scol_check php tests/MySql/StudentCorrectionChecks.php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function correctionCheck(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label.' failed');
    }
    echo $label.': PASS'.PHP_EOL;
}
function correctionRejected(callable $operation, string $sqlState, string $label): void
{
    try {
        $operation();
    } catch (QueryException $error) {
        correctionCheck($error->getCode() === $sqlState, $label);

        return;
    }
    throw new RuntimeException($label.' was not rejected');
}
if (DB::connection()->getDriverName() !== 'mysql' || ! str_contains((string) DB::connection()->getDatabaseName(), 'check')) {
    throw new RuntimeException('A disposable MySQL database whose name contains "check" is required.');
}

app(RolesAndPermissionsSeeder::class)->run();
$tag = 'CORR-'.bin2hex(random_bytes(4));
$establishment = Establishment::create(['code' => $tag, 'name' => 'Correction check']);
$year = AcademicYear::create(['name' => $tag, 'is_current' => true, 'is_active' => true]);
$student = Student::factory()->create(['phone' => '0341234567', 'cin' => null]);
StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'establishment_id' => $establishment->id, 'status' => 'active']);
$user = User::factory()->create()->assignRole('student');
$user->forceFill(['student_id' => $student->id])->save();
$staff = User::factory()->create()->assignRole('establishment');
$staff->forceFill(['establishment_id' => $establishment->id])->save();

$correction = app(RequestStudentCorrection::class)->handle($user, StudentCorrectionField::Phone, '0381234567', null);
$row = fn (array $overrides = []) => ['student_id' => $student->id, 'user_id' => $user->id, 'field' => 'phone', 'proposed_value' => 'x', 'current_fingerprint' => str_repeat('0', 64), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(), ...$overrides];
correctionRejected(fn () => DB::table('student_correction_requests')->insert($row()), '23000', 'unique pending request per student and field');
DB::table('student_correction_requests')->insert($row(['status' => 'cancelled']));
DB::table('student_correction_requests')->insert($row(['status' => 'rejected']));
correctionCheck(true, 'decided requests for the same field remain allowed');
correctionRejected(fn () => DB::table('student_correction_requests')->insert($row(['student_id' => 999999999, 'field' => 'address'])), '23000', 'student foreign key');
correctionRejected(fn () => DB::table('students')->where('id', $student->id)->delete(), '23000', 'student deletion restricted by corrections');

// A second connection holds the correction row: the review must wait for it, then give up without side effects.
config(['database.connections.mysql_lock' => config('database.connections.mysql')]);
$other = DB::connection('mysql_lock');
$other->beginTransaction();
$other->table('student_correction_requests')->where('id', $correction->id)->lockForUpdate()->first();
DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
$started = microtime(true);
try {
    app(ReviewStudentCorrection::class)->handle($staff, $correction, true, null);
    throw new RuntimeException('review did not wait for the row lock');
} catch (QueryException $error) {
    correctionCheck(str_contains($error->getMessage(), 'Lock wait timeout'), 'review waits on the locked correction row');
}
correctionCheck(microtime(true) - $started >= 1, 'lock wait measured');
correctionCheck($student->fresh()->phone === '0341234567' && $correction->fresh()->status === StudentCorrectionStatus::Pending, 'no partial write after lock timeout');
$other->rollBack();

app(ReviewStudentCorrection::class)->handle($staff, $correction, true, null);
correctionCheck($student->fresh()->phone === '0381234567' && $correction->fresh()->status === StudentCorrectionStatus::Approved, 'review succeeds once the lock is released');

// A failure after the identity write rolls the whole decision back on InnoDB.
$failing = app(RequestStudentCorrection::class)->handle($user, StudentCorrectionField::Address, 'Lot check', null);
StudentCorrectionRequest::updating(function (StudentCorrectionRequest $model) {
    if ($model->status === StudentCorrectionStatus::Approved) {
        throw new RuntimeException('simulated failure');
    }
});
try {
    app(ReviewStudentCorrection::class)->handle($staff, $failing, true, null);
} catch (RuntimeException) {
}
correctionCheck($student->fresh()->address !== 'Lot check' && $failing->fresh()->status === StudentCorrectionStatus::Pending, 'transaction rollback on failure');
echo 'All MySQL correction checks passed.'.PHP_EOL;
