<?php

use App\Actions\Imports\AnalyzeStudentImport;
use App\Actions\Imports\CommitStudentImport;
use App\Actions\Imports\UploadStudentImport;
use App\Enums\StudentImportStatus;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

// Run against the configured MySQL database; every temporary DB write is rolled back.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function importCheck(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label.' failed');
    }
    echo $label.': PASS'.PHP_EOL;
}
function importConstraint(callable $operation, string $label): void
{
    try {
        $operation();
    } catch (QueryException $error) {
        if ($error->getCode() !== '23000') {
            throw $error;
        }
        importCheck(true, $label);

        return;
    }
    throw new RuntimeException($label.' was not rejected');
}
if (DB::connection()->getDriverName() !== 'mysql') {
    throw new RuntimeException('MySQL is required.');
}
$tag = 'IMPORT-CHECK-'.bin2hex(random_bytes(5));
$before = [Student::count(), StudentRecord::count(), StudentImport::count(), Activity::count()];
$files = [];
$path = tempnam(sys_get_temp_dir(), 'student-import-check-');
DB::beginTransaction();
try {
    $establishment = Establishment::create(['code' => $tag, 'name' => 'Temporary import check']);
    $year = AcademicYear::create(['name' => $tag]);
    $level = Level::create(['code' => $tag, 'name' => 'Temporary import check']);
    $actor = User::factory()->create()->assignRole('superadmin');
    // Columns of the establishment file required by the analysis; the tag also serves as an unusual name.
    $header = "last_name;mother_name;first_names;sex;birth_date;nationality;repeat_code;level\n";
    $csv = $header.'Temporary '.$tag.';'.$tag.';Check;M;2001-01-01;Malagasy;N;'.$tag."\n";
    file_put_contents($path, $csv);
    $upload = fn () => app(UploadStudentImport::class)->handle($actor, new UploadedFile($path, 'check.csv', 'text/csv', null, true), $establishment->id, $year->id);
    $import = $upload();
    $files[] = $import->stored_path;
    importCheck($import->rows()->sole()->raw_data[1] === $tag, 'MySQL JSON staging round trip');
    importConstraint(fn () => StudentImportRow::create(['student_import_id' => $import->id, 'row_number' => 2, 'raw_data' => []]), 'Unique import/row number');
    foreach (['establishment_id', 'academic_year_id', 'user_id'] as $field) {
        importConstraint(fn () => DB::table('student_imports')->where('id', $import->id)->update([$field => 999999999]), 'Import foreign key '.$field);
    }
    importConstraint(fn () => DB::table('student_import_rows')->where('student_import_id', $import->id)->update(['matched_student_id' => 999999999]), 'Matched student foreign key');
    importConstraint(fn () => DB::table('student_import_rows')->where('student_import_id', $import->id)->update(['matched_student_record_id' => 999999999]), 'Matched record foreign key');
    app(AnalyzeStudentImport::class)->handle($actor, $import, $import->mapping);
    app(CommitStudentImport::class)->handle($actor, $import);
    importCheck($import->fresh()->status === StudentImportStatus::Completed && $import->fresh()->declared_rows === 1, 'MySQL transactional commit');
    $after = [Student::count(), StudentRecord::count(), Activity::count()];
    app(CommitStudentImport::class)->handle($actor, $import);
    importCheck($after === [Student::count(), StudentRecord::count(), Activity::count()], 'MySQL repeat commit no-op');
    $second = $upload();
    $files[] = $second->stored_path;
    app(AnalyzeStudentImport::class)->handle($actor, $second, $second->mapping);
    importCheck($second->fresh()->duplicate_rows === 1, 'MySQL repeated file is a duplicate');
    // The keyed lock must exclude a second connection until the transaction ends.
    $key = DB::table('student_identity_locks')->value('key');
    $connectionConfig = config('database.connections.mysql');
    config(['database.connections.import_lock_probe' => $connectionConfig]);
    $probe = DB::connection('import_lock_probe');
    $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
    $probe->beginTransaction();
    try {
        try {
            $probe->table('student_identity_locks')->where('key', $key)->lockForUpdate()->first();
            throw new RuntimeException('Targeted lock did not block.');
        } catch (QueryException $error) {
            if (($error->errorInfo[1] ?? null) !== 1205) {
                throw $error;
            } importCheck(true, 'MySQL targeted identity lock excludes a concurrent writer');
        }
    } finally {
        $probe->rollBack();
        DB::purge('import_lock_probe');
    }
    // Failure on the second declaration must undo the first student and its audit.
    file_put_contents($path, $header.'Rollback one '.$tag.';'.$tag.'-R1;Check;F;2001-01-01;Malagasy;N;'.$tag."\n".'Rollback two '.$tag.';'.$tag.'-R2;Check;M;2001-01-01;Malagasy;R;'.$tag."\n");
    $failed = $upload();
    $files[] = $failed->stored_path;
    app(AnalyzeStudentImport::class)->handle($actor, $failed, $failed->mapping);
    $snapshot = [Student::count(), StudentRecord::count(), Activity::count()];
    $count = 0;
    StudentRecord::creating(function () use (&$count) {
        if (++$count === 2) {
            throw new RuntimeException('Probe rollback');
        }
    });
    try {
        app(CommitStudentImport::class)->handle($actor, $failed);
        throw new RuntimeException('Failure injection did not fail.');
    } catch (ValidationException $error) {
        importCheck($snapshot === [Student::count(), StudentRecord::count(), Activity::count()] && $failed->fresh()->status === StudentImportStatus::Failed && $failed->rows()->whereNotNull('resolution')->count() === 0, 'MySQL whole batch/staging/audit rollback');
    }
} finally {
    DB::rollBack();
    foreach ($files as $file) {
        Storage::disk('student_imports')->delete($file);
    }
    unlink($path);
}
importCheck($before === [Student::count(), StudentRecord::count(), StudentImport::count(), Activity::count()], 'All temporary data rolled back');
