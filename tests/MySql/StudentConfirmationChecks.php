<?php

use App\Actions\Corrections\RequestStudentCorrection;
use App\Actions\StudentPortal\ConfirmStudentInformation;
use App\Enums\StudentCorrectionField;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentInformationConfirmation;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentConfirmationContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

// Commits real rows to exercise row locks across two connections: run only on a disposable, migrated database
// whose name contains "check", e.g. DB_DATABASE=umg_scol_check php tests/MySql/StudentConfirmationChecks.php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function confirmationCheck(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label.' failed');
    }
    echo $label.': PASS'.PHP_EOL;
}
function confirmationRejected(callable $operation, string $label, string $constraint = ''): void
{
    try {
        $operation();
    } catch (QueryException $error) {
        confirmationCheck($error->getCode() === '23000' && str_contains($error->getMessage(), $constraint), $label);

        return;
    }
    throw new RuntimeException($label.' was not rejected');
}
/** Another connection holds a row lock: the operation must wait for it, then give up. */
function confirmationWaits(string $table, int $id, callable $operation, string $label): void
{
    $other = DB::connection('mysql_lock');
    $other->beginTransaction();
    $other->table($table)->where('id', $id)->lockForUpdate()->first();
    $started = microtime(true);
    try {
        $operation();
        throw new RuntimeException($label.' did not wait for the row lock');
    } catch (QueryException $error) {
        confirmationCheck(str_contains($error->getMessage(), 'Lock wait timeout') && microtime(true) - $started >= 1, $label);
    } finally {
        $other->rollBack();
    }
}
if (DB::connection()->getDriverName() !== 'mysql' || ! str_contains((string) DB::connection()->getDatabaseName(), 'check')) {
    throw new RuntimeException('A disposable MySQL database whose name contains "check" is required.');
}

app(RolesAndPermissionsSeeder::class)->run();
$tag = 'CONF-'.bin2hex(random_bytes(4));
$establishment = Establishment::create(['code' => $tag, 'name' => 'Confirmation check']);
AcademicYear::query()->where('is_current', true)->update(['is_current' => false]);
$year = AcademicYear::create(['name' => $tag, 'is_current' => true, 'is_active' => true]);
$student = Student::factory()->create(['phone' => '0341234567', 'cin' => null]);
$record = StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'establishment_id' => $establishment->id, 'status' => 'active']);
$user = User::factory()->create()->assignRole('student');
$user->forceFill(['student_id' => $student->id])->save();
$version = fn () => (string) StudentConfirmationContext::load($student->fresh())->forStudent()['version'];
$count = fn () => StudentInformationConfirmation::query()->where('student_id', $student->id)->count();

config(['database.connections.mysql_lock' => config('database.connections.mysql')]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

// Locks: the confirmation waits for the account, the student and the current declaration, and writes nothing on timeout.
confirmationWaits('users', $user->id, fn () => app(ConfirmStudentInformation::class)->handle($user, $version()), 'confirmation waits on the account row (concurrent correction request)');
confirmationWaits('students', $student->id, fn () => app(ConfirmStudentInformation::class)->handle($user, $version()), 'confirmation waits on the student row (identity update in progress)');
confirmationWaits('student_records', $record->id, fn () => app(ConfirmStudentInformation::class)->handle($user, $version()), 'confirmation waits on the current declaration (declaration update in progress)');
confirmationCheck($count() === 0, 'no confirmation written after lock timeouts');

// A correction request waits for the account row held by a confirmation in progress: they never interleave.
confirmationWaits('users', $user->id, fn () => app(RequestStudentCorrection::class)->handle($user, StudentCorrectionField::Phone, '0381234567', null), 'correction request waits on the same account row');
confirmationCheck(StudentCorrectionRequest::query()->where('student_id', $student->id)->doesntExist(), 'no correction written after lock timeout');

// Full rollback when the audit write fails.
Activity::creating(function (Activity $activity) {
    if ($activity->event === 'student_information_confirmed') {
        throw new RuntimeException('audit unavailable');
    }
});
try {
    app(ConfirmStudentInformation::class)->handle($user, $version());
} catch (RuntimeException) {
}
confirmationCheck($count() === 0, 'full rollback when the audit write fails');
Activity::flushEventListeners();

$first = $version();
app(ConfirmStudentInformation::class)->handle($user, $first);
app(ConfirmStudentInformation::class)->handle($user, $first);
confirmationCheck($count() === 1 && Activity::query()->where('event', 'student_information_confirmed')->where('subject_id', StudentInformationConfirmation::query()->where('student_id', $student->id)->value('id'))->count() === 1, 'same version confirmed twice: one row, one audit line');
$confirmation = StudentInformationConfirmation::query()->where('student_id', $student->id)->sole();
$row = fn (array $overrides = []) => ['student_id' => $student->id, 'sequence' => 1, 'user_id' => $user->id, 'student_record_id' => $record->id, 'academic_year_id' => $year->id, 'information_fingerprint' => str_repeat('0', 64), 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(), ...$overrides];
confirmationRejected(fn () => DB::table('student_information_confirmations')->insert($row()), 'unique student + sequence (concurrent duplicate)');
confirmationRejected(fn () => DB::table('student_information_confirmations')->insert($row(['sequence' => 9, 'student_id' => 999999999])), 'student foreign key');
confirmationRejected(fn () => DB::table('student_information_confirmations')->insert($row(['sequence' => 9, 'student_record_id' => 999999999])), 'student record foreign key');
confirmationRejected(fn () => DB::table('student_information_confirmations')->insert($row(['sequence' => 9, 'academic_year_id' => 999999999])), 'academic year foreign key');
confirmationRejected(fn () => DB::table('student_information_confirmations')->insert($row(['sequence' => 9, 'user_id' => 999999999])), 'user foreign key');
confirmationRejected(fn () => DB::table('students')->where('id', $student->id)->delete(), 'student deletion restricted by confirmations', 'student_information_confirmations_student_id_foreign');
confirmationRejected(fn () => DB::table('student_records')->where('id', $record->id)->delete(), 'declaration deletion restricted by confirmations', 'student_information_confirmations_student_record_id_foreign');

// Stale after an update committed by another connection, then a new confirmation keeps the history.
DB::connection('mysql_lock')->table('students')->where('id', $student->id)->update(['phone' => '0329998877']);
confirmationCheck(StudentConfirmationContext::load($student->fresh())->status() === StudentConfirmationContext::STALE, 'confirmation stale after a committed update');
app(ConfirmStudentInformation::class)->handle($user, $version());
confirmationCheck($count() === 2 && $confirmation->fresh()->information_fingerprint !== StudentInformationConfirmation::query()->where('student_id', $student->id)->where('sequence', 2)->value('information_fingerprint'), 'new version confirmed, history kept');

echo 'All MySQL confirmation checks passed.'.PHP_EOL;
