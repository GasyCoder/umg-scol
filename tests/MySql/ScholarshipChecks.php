<?php

use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Actions\Scholarships\TransitionScholarshipApplication;
use App\Enums\ScholarshipApplicationStatus;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationStatusHistory;
use App\Models\ScholarshipCampaign;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Commits real rows to exercise row locks across two connections: run only on a disposable, migrated database
// whose name contains "check", e.g. DB_DATABASE=umg_scol_check php tests/MySql/ScholarshipChecks.php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function scholarshipCheck(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label.' failed');
    }
    echo $label.': PASS'.PHP_EOL;
}
function scholarshipRejected(callable $operation, string $label): void
{
    try {
        $operation();
    } catch (QueryException $error) {
        scholarshipCheck($error->getCode() === '23000', $label);

        return;
    }
    throw new RuntimeException($label.' was not rejected');
}
/** Runs $operation while a second connection holds a row lock on $table/$id; expects a lock wait timeout. */
function scholarshipWaits(string $table, int $id, callable $operation, string $label): void
{
    $other = DB::connection('mysql_lock');
    $other->beginTransaction();
    $other->table($table)->where('id', $id)->lockForUpdate()->first();
    try {
        $operation();
        throw new RuntimeException($label.': no lock wait');
    } catch (QueryException $error) {
        scholarshipCheck(str_contains($error->getMessage(), 'Lock wait timeout'), $label);
    } finally {
        $other->rollBack();
    }
}
if (DB::connection()->getDriverName() !== 'mysql' || ! str_contains((string) DB::connection()->getDatabaseName(), 'check')) {
    throw new RuntimeException('A disposable MySQL database whose name contains "check" is required.');
}
config(['database.connections.mysql_lock' => config('database.connections.mysql')]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

app(RolesAndPermissionsSeeder::class)->run();
$central = User::factory()->create()->assignRole('central_scholarship_office');
$year = AcademicYear::factory()->create(['is_active' => true]);
$establishment = Establishment::factory()->create();
$level = Level::factory()->create();
$declared = function () use ($year, $establishment, $level): Student {
    $student = Student::factory()->create(['is_active' => true]);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'establishment_id' => $establishment->id, 'level_id' => $level->id, 'program_id' => null, 'status' => 'active']);

    return $student;
};
$campaign = ScholarshipCampaign::factory()->create(['academic_year_id' => $year->id, 'status' => 'open', 'created_by' => $central->id]);
$receive = fn (Student $student, ?string $ddb = null) => app(ReceiveScholarshipApplication::class)->handle($central, $campaign->id, $student->id, $ddb, null);

$first = $receive($declared(), 'DDB 1');
$row = fn (array $overrides) => [...$first->only(['scholarship_campaign_id', 'student_id', 'student_record_id', 'received_by']), 'application_number' => 'CHK-'.bin2hex(random_bytes(3)), 'sequence' => random_int(1000, 999999), 'status' => 'pending', 'received_at' => now(), 'created_at' => now(), 'updated_at' => now(), ...$overrides];
scholarshipRejected(fn () => DB::table('scholarship_applications')->insert($row([])), 'unique campaign + student');
$other = $declared();
scholarshipRejected(fn () => DB::table('scholarship_applications')->insert($row(['student_id' => $other->id, 'ddb_key' => 'DDB1'])), 'unique campaign + DDB key');
scholarshipRejected(fn () => DB::table('scholarship_applications')->insert($row(['student_id' => $other->id, 'sequence' => $first->sequence])), 'unique campaign + sequence');
scholarshipRejected(fn () => DB::table('scholarship_applications')->insert($row(['student_id' => $other->id, 'application_number' => $first->application_number])), 'unique application number');
$receive($declared());
$receive($declared());
scholarshipCheck(ScholarshipApplication::query()->whereNull('ddb_key')->count() === 2, 'several files without DDB in one campaign');
scholarshipRejected(fn () => DB::table('scholarship_application_status_histories')->insert(['scholarship_application_id' => 999999999, 'to_status' => 'pending', 'actor_id' => $central->id, 'created_at' => now()]), 'status history foreign key');
scholarshipRejected(fn () => DB::table('students')->where('id', $first->student_id)->delete(), 'student deletion restricted by files');

$late = $declared();
scholarshipWaits('scholarship_campaigns', $campaign->id, fn () => $receive($late), 'reception waits on the campaign row (closing in progress)');
scholarshipCheck(! ScholarshipApplication::query()->where('student_id', $late->id)->exists() && $campaign->fresh()->last_application_sequence === 3, 'no file nor sequence consumed after lock timeout');

scholarshipWaits('scholarship_applications', $first->id, fn () => app(TransitionScholarshipApplication::class)->handle($central, $first, ScholarshipApplicationStatus::Rejected, 'Décision concurrente.'), 'decision waits on the file row');
scholarshipCheck($first->fresh()->status === ScholarshipApplicationStatus::Pending && ScholarshipApplicationStatusHistory::query()->where('scholarship_application_id', $first->id)->count() === 1, 'no partial decision after lock timeout');

ScholarshipApplicationStatusHistory::creating(function (ScholarshipApplicationStatusHistory $history) {
    if ($history->to_status === ScholarshipApplicationStatus::Rejected) {
        throw new RuntimeException('simulated failure');
    }
});
try {
    app(TransitionScholarshipApplication::class)->handle($central, $first, ScholarshipApplicationStatus::Rejected, 'Échec simulé après mise à jour.');
} catch (RuntimeException) {
}
scholarshipCheck($first->fresh()->status === ScholarshipApplicationStatus::Pending && $first->fresh()->decided_at === null, 'full rollback when the history write fails');
echo 'All MySQL scholarship checks passed.'.PHP_EOL;
