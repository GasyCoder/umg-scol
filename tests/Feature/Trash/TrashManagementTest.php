<?php

use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\Reporting\InstitutionalMetrics;
use App\Support\Reporting\ReportScope;
use App\Support\Trash\FileDeletionOutbox;
use App\Support\Trash\TrashManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('student_imports');
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->actingAs($this->admin);
});
function trashImport(TestCase $test): StudentImport
{
    $import = StudentImport::create(['establishment_id' => Establishment::factory()->create()->id, 'academic_year_id' => AcademicYear::factory()->create()->id, 'user_id' => $test->admin->id, 'original_filename' => 'private.csv', 'stored_path' => 'files/private-test.csv', 'disk' => 'student_imports']);
    StudentImportRow::create(['student_import_id' => $import->id, 'row_number' => 2, 'raw_data' => ['PRIVATE-CIN', 'secret@example.test']]);
    Storage::disk('student_imports')->put($import->stored_path, 'private-data');

    return $import;
}
function trashModel(TestCase $test, string $kind, Model $model): void
{
    $test->delete('/trash/'.$kind.'/'.$model->uuid)->assertSessionHasNoErrors()->assertRedirect();
}
it('soft deletes and restores unused references', function (string $kind, string $class) {
    $model = $class::factory()->create();
    trashModel($this, $kind, $model);
    expect($class::query()->whereKey($model->id)->exists())->toBeFalse()->and($class::withTrashed()->find($model->id)->deleted_by)->toBe($this->admin->id);
    $this->post('/trash/'.$kind.'/'.$model->uuid.'/restore')->assertSessionHasNoErrors();
    expect($class::query()->whereKey($model->id)->exists())->toBeTrue();
})->with([['establishments', Establishment::class], ['academic-years', AcademicYear::class], ['levels', Level::class], ['mentions', Mention::class], ['programs', Program::class]]);
it('refuses every direct delete force delete or restore outside the manager', function (string $operation) {
    $model = Level::factory()->create();
    if ($operation === 'restore') {
        trashModel($this, 'levels', $model);
    }
    expect(fn () => Level::withTrashed()->find($model->id)->{$operation}())->toThrow(LogicException::class);
})->with(['delete', 'forceDelete', 'restore']);
it('refuses trash endpoints for other roles even with direct permissions', function (string $role) {
    $model = Level::factory()->create();
    $user = User::factory()->create()->assignRole($role);
    $user->givePermissionTo(['trash.manage', 'trash.purge']);
    $this->actingAs($user)->get('/trash')->assertForbidden();
    $this->delete('/trash/levels/'.$model->uuid)->assertForbidden();
    $this->post('/trash/levels/'.$model->uuid.'/restore')->assertForbidden();
    $this->delete('/trash/levels/'.$model->uuid.'/purge')->assertForbidden();
})->with(['student', 'establishment', 'central_scholarship_office']);
it('honours permission revocation even for a superadmin', function () {
    Role::findByName('superadmin')->revokePermissionTo('trash.manage');
    $this->actingAs($this->admin->fresh())->get('/trash')->assertForbidden();
});
it('separates trash management from permanent purge rights', function () {
    Role::findByName('superadmin')->revokePermissionTo('trash.purge');
    $this->actingAs($this->admin->fresh());
    $model = Level::factory()->create();
    trashModel($this, 'levels', $model);
    $this->delete('/trash/levels/'.$model->uuid.'/purge')->assertForbidden();
    $this->post('/trash/levels/'.$model->uuid.'/restore')->assertSessionHasNoErrors();
});
it('rejects numeric identifiers and unknown model types', function () {
    $model = Level::factory()->create();
    $this->delete('/trash/levels/'.$model->id)->assertNotFound();
    $this->delete('/trash/users/'.$model->uuid)->assertNotFound();
});
it('keeps used referentials protected even when their children are in trash', function () {
    $establishment = Establishment::factory()->create();
    $program = Program::factory()->create(['establishment_id' => $establishment->id]);
    $this->delete('/trash/establishments/'.$establishment->uuid)->assertSessionHasErrors('trash');
    trashModel($this, 'programs', $program);
    $this->delete('/trash/establishments/'.$establishment->uuid)->assertSessionHasErrors('trash');
});
it('moves student declarations together and restores only the same batch', function () {
    $student = Student::factory()->create();
    $before = StudentRecord::factory()->create(['student_id' => $student->id]);
    $together = StudentRecord::factory()->create(['student_id' => $student->id]);
    trashModel($this, 'student-records', $before);
    trashModel($this, 'students', $student);
    expect(StudentRecord::where('student_id', $student->id)->count())->toBe(0);
    $this->post('/trash/students/'.$student->uuid.'/restore')->assertSessionHasNoErrors();
    expect(StudentRecord::where('student_id', $student->id)->count())->toBe(1)->and(StudentRecord::find($together->id))->not->toBeNull()->and(StudentRecord::find($before->id))->toBeNull();
});
it('rejects restoring an identity conflicting with a newly created identity', function (string $field, string $value) {
    $student = Student::factory()->create([$field => $value]);
    trashModel($this, 'students', $student);
    Student::factory()->create([$field => $value]);
    $this->post('/trash/students/'.$student->uuid.'/restore')->assertSessionHasErrors('trash');
    expect(Student::find($student->id))->toBeNull();
})->with([['cin', '101010101010'], ['phone', '0341010101'], ['email', 'conflict@example.test']]);
it('makes a deleted active declaration release its uniqueness slot and rejects conflicting restoration', function () {
    $student = Student::factory()->create();
    $record = StudentRecord::factory()->create(['student_id' => $student->id]);
    trashModel($this, 'student-records', $record);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $record->academic_year_id, 'establishment_id' => $record->establishment_id]);
    $this->post('/trash/student-records/'.$record->uuid.'/restore')->assertSessionHasErrors('trash');
    expect(StudentRecord::query()->where('student_id', $student->id)->count())->toBe(1);
});
it('never restores a declaration before its student', function () {
    $student = Student::factory()->create();
    $record = StudentRecord::factory()->create(['student_id' => $student->id]);
    trashModel($this, 'students', $student);
    $this->post('/trash/student-records/'.$record->uuid.'/restore')->assertSessionHasErrors('trash');
});
it('rejects restoring a previous current year when another one became current', function () {
    $year = AcademicYear::factory()->create(['is_current' => true]);
    trashModel($this, 'academic-years', $year);
    AcademicYear::factory()->create(['is_current' => true]);
    $this->post('/trash/academic-years/'.$year->uuid.'/restore')->assertSessionHasErrors('trash');
    expect(AcademicYear::where('is_current', true)->count())->toBe(1);
});
it('archives imports reversibly without touching their students or private file', function () {
    $import = trashImport($this);
    $student = Student::factory()->create();
    trashModel($this, 'imports', $import);
    expect(StudentImport::count())->toBe(0)->and(Student::find($student->id))->not->toBeNull();
    Storage::disk('student_imports')->assertExists($import->stored_path);
    $this->post('/trash/imports/'.$import->uuid.'/restore')->assertSessionHasNoErrors();
    expect(StudentImport::count())->toBe(1);
});
it('rejects removing an import currently analyzing or committing', function (string $status) {
    $import = trashImport($this);
    $import->update(['status' => $status]);
    $this->delete('/trash/imports/'.$import->uuid)->assertSessionHasErrors('trash');
})->with(['analyzing', 'committing']);
it('purges only trashed unused entries and keeps audit history', function () {
    $level = Level::factory()->create();
    $this->delete('/trash/levels/'.$level->uuid.'/purge')->assertSessionHasErrors('trash');
    trashModel($this, 'levels', $level);
    $this->delete('/trash/levels/'.$level->uuid.'/purge')->assertSessionHasNoErrors();
    expect(Level::withTrashed()->find($level->id))->toBeNull()->and(Activity::where('event', 'purged')->where('subject_id', $level->id)->exists())->toBeTrue();
});
it('does not purge a student account attachment or confirmation history', function () {
    $student = Student::factory()->create();
    $user = User::factory()->create()->assignRole('student');
    $user->forceFill(['student_id' => $student->id])->save();
    trashModel($this, 'students', $student);
    $this->delete('/trash/students/'.$student->uuid.'/purge')->assertSessionHasErrors('trash');
    expect(Student::withTrashed()->find($student->id))->not->toBeNull();
});
it('keeps private data out of trash audit events', function () {
    $student = Student::factory()->create(['cin' => 'PRIVATE-CIN', 'phone' => '0349999999', 'email' => 'private@example.test']);
    trashModel($this, 'students', $student);
    $this->post('/trash/students/'.$student->uuid.'/restore')->assertSessionHasNoErrors();
    $logs = Activity::where('log_name', 'trash')->get();
    expect($logs->pluck('event')->all())->toBe(['trashed', 'restored'])->and($logs->last()->causer_id)->toBe($this->admin->id);
    foreach (['PRIVATE-CIN', '0349999999', 'private@example.test'] as $value) {
        expect($logs->toJson())->not->toContain($value);
    }
});
it('rolls back parent children and audits when an audit write fails', function () {
    $student = Student::factory()->create();
    $record = StudentRecord::factory()->create(['student_id' => $student->id]);
    $before = Activity::count();
    Activity::creating(function ($event) {
        if ($event->log_name === 'trash') {
            throw new RuntimeException('Probe');
        }
    });
    try {
        expect(fn () => app(TrashManager::class)->handle($this->admin, 'students', $student->uuid, 'trash'))->toThrow(RuntimeException::class);
    } finally {
        Activity::flushEventListeners();
        Activity::clearBootedModels();
    }
    expect(Student::find($student->id))->not->toBeNull()->and(StudentRecord::find($record->id))->not->toBeNull()->and(Activity::count())->toBe($before)->and(TrashManager::isRunning())->toBeFalse();
});
it('paginates the trash and omits original staging values', function () {
    $import = trashImport($this);
    trashModel($this, 'imports', $import);
    $this->get('/trash?kind=imports')->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->missing('records.data.0.raw_data')->missing('records.data.0.stored_path'));
});
it('rejects a trashed referential in an administrative payload', function () {
    $level = Level::factory()->create();
    trashModel($this, 'levels', $level);
    $this->post('/students', ['last_name' => 'Nom', 'academic_year_id' => AcademicYear::factory()->create()->id, 'establishment_id' => Establishment::factory()->create()->id, 'level_id' => $level->id])->assertSessionHasErrors('level_id');
});

it('purges an import and its file staging while retaining imported identities', function () {
    $import = trashImport($this);
    $student = Student::factory()->create();
    trashModel($this, 'imports', $import);
    $this->delete('/trash/imports/'.$import->uuid.'/purge')->assertSessionHasNoErrors();
    expect(StudentImport::withTrashed()->find($import->id))->toBeNull()->and(StudentImportRow::count())->toBe(0)->and(Student::find($student->id))->not->toBeNull();
    Storage::disk('student_imports')->assertMissing($import->stored_path);
});
it('keeps storage cleanup retriable when the disk is unavailable', function () {
    $id = DB::table('pending_file_deletions')->insertGetId(['disk' => 'unconfigured', 'path' => 'files/private.csv', 'created_at' => now(), 'updated_at' => now()]);
    expect(app(FileDeletionOutbox::class)->clean($id))->toBeFalse();
    expect(DB::table('pending_file_deletions')->where('id', $id)->value('attempts'))->toBe(1);
    config(['filesystems.disks.unconfigured' => config('filesystems.disks.student_imports')]);
    expect(app(FileDeletionOutbox::class)->clean($id))->toBeTrue()->and(DB::table('pending_file_deletions')->where('id', $id)->exists())->toBeFalse();
});
it('hides a trashed student from the student API and corrections', function () {
    $student = Student::factory()->create();
    $user = User::factory()->create()->assignRole('student');
    $user->forceFill(['student_id' => $student->id])->save();
    trashModel($this, 'students', $student);
    $this->actingAs($user, 'student')->withSession(['student_portal' => true]);
    $base = 'http://'.config('portals.bourse_domain').'/api/student';
    $this->getJson($base.'/me')->assertForbidden();
    $this->getJson($base.'/corrections')->assertForbidden();
});
it('keeps the academic year, student and situation of a paper file protected', function () {
    $student = Student::factory()->create();
    $record = StudentRecord::factory()->create(['student_id' => $student->id]);
    $record->academicYear->update(['is_current' => true, 'is_active' => true]);
    $application = app(ReceiveScholarshipApplication::class)->handle($this->admin, $student->id, null, null);
    $this->delete('/trash/academic-years/'.$record->academicYear->uuid)->assertSessionHasErrors('trash');
    $this->delete('/trash/students/'.$student->uuid)->assertSessionHasErrors('trash');
    $this->delete('/trash/student-records/'.$record->uuid)->assertSessionHasErrors('trash');
    trashModel($this, 'applications', $application);
    $this->post('/trash/applications/'.$application->uuid.'/restore')->assertSessionHasNoErrors();
});
it('rejects purging any paper file already in review', function () {
    $student = Student::factory()->create();
    $record = StudentRecord::factory()->create(['student_id' => $student->id]);
    $record->academicYear->update(['is_current' => true, 'is_active' => true]);
    $application = app(ReceiveScholarshipApplication::class)->handle($this->admin, $student->id, null, null);
    $application->update(['status' => 'in_review']);
    trashModel($this, 'applications', $application);
    $this->delete('/trash/applications/'.$application->uuid.'/purge')->assertSessionHasErrors('trash');
    expect(ScholarshipApplication::withTrashed()->find($application->id))->not->toBeNull();
});
it('removes trashed records from aggregated reports', function () {
    $year = AcademicYear::factory()->create(['is_current' => true]);
    $student = Student::factory()->create();
    $record = StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $year->id]);
    $scope = ReportScope::for($this->admin);
    expect((new InstitutionalMetrics($scope))->students()['declarations'])->toBe(1);
    trashModel($this, 'students', $student);
    expect((new InstitutionalMetrics($scope))->students()['declarations'])->toBe(0);
});

it('never widens an establishment account scope through a mixed privileged role', function () {
    $user = User::factory()->create()->assignRole(['establishment', 'developer']);
    $user->forceFill(['establishment_id' => Establishment::factory()->create()->id])->save();
    $this->actingAs($user)->get('/trash')->assertForbidden();
});
it('frees a deleted declaration request reference and refuses its conflicting restoration', function () {
    $record = StudentRecord::factory()->create(['scholarship_request_reference' => 'DB-TRASH']);
    trashModel($this, 'student-records', $record);
    StudentRecord::factory()->create(['academic_year_id' => $record->academic_year_id, 'establishment_id' => $record->establishment_id, 'scholarship_request_reference' => 'DB-TRASH']);
    $this->post('/trash/student-records/'.$record->uuid.'/restore')->assertSessionHasErrors('trash');
    expect(StudentRecord::find($record->id))->toBeNull()->and(StudentRecord::where('scholarship_request_reference', 'DB-TRASH')->count())->toBe(1);
});
