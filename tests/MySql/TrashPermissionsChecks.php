<?php

use App\Actions\Permissions\SaveRolePermissions;
use App\Models\Activity;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\Permissions\PermissionCatalogue;
use App\Support\Trash\TrashManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDriverName() !== 'mysql') {
    throw new RuntimeException('MySQL required.');
}
function trashMysqlCheck(bool $ok, string $label): void
{
    if (! $ok) {
        throw new RuntimeException($label.' failed.');
    }echo $label.': PASS'.PHP_EOL;
}
$before = [Student::count(), StudentRecord::count(), User::count(), Activity::count(), Level::count()];
DB::beginTransaction();
try {
    $admin = User::factory()->create()->assignRole('superadmin');
    $student = Student::factory()->create();
    $record = StudentRecord::factory()->create(['student_id' => $student->id]);
    app(TrashManager::class)->handle($admin, 'student-records', $record->uuid, 'trash');
    $replacement = StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $record->academic_year_id, 'establishment_id' => $record->establishment_id]);
    trashMysqlCheck(StudentRecord::where('student_id', $student->id)->count() === 1, 'MySQL live declaration unique slot released');
    try {
        app(TrashManager::class)->handle($admin, 'student-records', $record->uuid, 'restore');
        throw new RuntimeException('Conflicting restore succeeded.');
    } catch (ValidationException) {
        trashMysqlCheck(true, 'MySQL conflicting declaration restore rejected');
    }
    app(TrashManager::class)->handle($admin, 'students', $student->uuid, 'trash');
    trashMysqlCheck(Student::find($student->id) === null && StudentRecord::where('student_id', $student->id)->count() === 0, 'MySQL student cascade hidden');
    app(TrashManager::class)->handle($admin, 'students', $student->uuid, 'restore');
    trashMysqlCheck(StudentRecord::where('student_id', $student->id)->count() === 1 && StudentRecord::find($record->id) === null, 'MySQL exact batch restored');
    $level = Level::factory()->create();
    app(TrashManager::class)->handle($admin, 'levels', $level->uuid, 'trash');
    app(TrashManager::class)->handle($admin, 'levels', $level->uuid, 'purge');
    trashMysqlCheck(Level::withTrashed()->find($level->id) === null && Activity::where('log_name', 'trash')->where('event', 'purged')->exists(), 'MySQL purge retains audit');
    $role = Role::findByName('developer');
    $current = $role->permissions()->pluck('name')->sort()->values()->all();
    $desired = array_values(array_diff($current, ['levels.manage']));
    app(SaveRolePermissions::class)->handle($admin, 'developer', $desired, PermissionCatalogue::version($current));
    app(RolesAndPermissionsSeeder::class)->run();
    trashMysqlCheck(! Role::findByName('developer')->hasPermissionTo('levels.manage'), 'MySQL permissions survive reseeding');
    config(['database.connections.trash_lock_probe' => config('database.connections.mysql')]);
    $probe = DB::connection('trash_lock_probe');
    $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
    $probe->beginTransaction();
    try {
        try {
            $probe->table('roles')->where('id', $role->id)->lockForUpdate()->first();
            throw new RuntimeException('Role lock did not block.');
        } catch (QueryException $error) {
            if (($error->errorInfo[1] ?? null) !== 1205) {
                throw $error;
            }trashMysqlCheck(true, 'MySQL concurrent role writer blocked');
        }
    } finally {
        $probe->rollBack();
        DB::purge('trash_lock_probe');
    }
    $count = Activity::count();
    $another = Student::factory()->create();
    $child = StudentRecord::factory()->create(['student_id' => $another->id]);
    $count = Activity::count();
    Activity::creating(function ($event) {
        if ($event->log_name === 'trash') {
            throw new RuntimeException('Rollback probe');
        }
    });
    try {
        app(TrashManager::class)->handle($admin, 'students', $another->uuid, 'trash');
        throw new RuntimeException('Failure probe not triggered.');
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'Rollback probe') {
            throw $error;
        }
    }
    trashMysqlCheck(Student::find($another->id) !== null && StudentRecord::find($child->id) !== null && Activity::count() === $count, 'MySQL parent child and audit rollback');
} finally {
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}
trashMysqlCheck($before === [Student::count(), StudentRecord::count(), User::count(), Activity::count(), Level::count()], 'All temporary database data rolled back');
