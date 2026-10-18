<?php

use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Actions\Scholarships\TransitionScholarshipApplication;
use App\Actions\Scholarships\UpdateScholarshipCheck;
use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCheckStatus;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Program;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationStatusHistory;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\Trash\TrashManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->establishment = Establishment::factory()->create(['name' => 'Faculté des Sciences']);
    $this->level = Level::factory()->create(['name' => 'Licence 1']);
    $this->program = Program::factory()->create(['establishment_id' => $this->establishment->id, 'name' => 'Biologie']);
    $this->year = AcademicYear::factory()->create(['name' => '2025-2026', 'is_current' => true, 'is_active' => true]);
    $this->student = Student::factory()->create([
        'last_name' => 'RABE', 'first_names' => 'Hery', 'sex' => 'M', 'birth_date' => '2003-04-05', 'cin' => '501012345678',
        'phone' => '0347654321', 'email' => 'hery@example.test', 'address' => 'Lot SECRET 7', 'is_active' => true,
    ]);
    $this->record = StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id, 'program_id' => $this->program->id, 'status' => 'active']);
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->staff = User::factory()->create()->assignRole('establishment');
    $this->staff->forceFill(['establishment_id' => $this->establishment->id])->save();
});

function receiveFile(TestCase $test, ?Student $student = null, ?string $ddb = null, ?User $actor = null): ScholarshipApplication
{
    return app(ReceiveScholarshipApplication::class)->handle($actor ?? $test->central, ($student ?? $test->student)->id, $ddb, null);
}
function completeChecks(ScholarshipApplication $application, User $actor, ScholarshipCheckStatus $status = ScholarshipCheckStatus::Ok): void
{
    foreach (array_keys(config('scholarships.checks')) as $key) {
        app(UpdateScholarshipCheck::class)->handle($actor, $application, $key, $status, null);
    }
}
function declaredStudent(TestCase $test, array $attributes = [], string $status = 'active'): Student
{
    $student = Student::factory()->create($attributes);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $test->year->id, 'establishment_id' => $test->establishment->id, 'level_id' => $test->level->id, 'program_id' => null, 'status' => $status]);

    return $student;
}

// Academic year of the reception
it('receives files only for the current academic year', function (array $year) {
    $this->year->update($year);
    expect(fn () => receiveFile($this))->toThrow(ValidationException::class, 'Aucune année universitaire courante');
    expect(ScholarshipApplication::count())->toBe(0);
    $this->actingAs($this->central)->get('/scholarships/reception')->assertInertia(fn (Assert $page) => $page->where('year', null));
})->with([[['is_current' => false]], [['is_active' => false]]]);

it('removes campaign routes and permissions while keeping reception available', function () {
    $this->actingAs($this->admin)->get('/scholarships/campaigns')->assertNotFound();
    $this->post('/scholarships/campaigns', ['name' => 'Ancienne campagne'])->assertNotFound();
    expect(array_filter(config('access.permissions'), fn (string $name) => str_starts_with($name, 'campaigns.')))->toBe([]);
    $this->get('/scholarships/reception')->assertOk();
});

// Reception
it('lets the central office receive a paper file with server-derived context', function () {
    $this->actingAs($this->central)->post('/scholarships/applications', ['student_id' => $this->student->id, 'academic_year_id' => 999, 'ddb_number' => '  ddb 0042 ', 'student_record_id' => 999, 'received_by' => $this->admin->id, 'status' => 'validated'])
        ->assertRedirect();
    $application = ScholarshipApplication::sole();
    expect($application->status)->toBe(ScholarshipApplicationStatus::Pending)->and($application->student_record_id)->toBe($this->record->id)
        ->and($application->academic_year_id)->toBe($this->year->id)
        ->and($application->received_by)->toBe($this->central->id)->and($application->received_at)->not->toBeNull()
        ->and($application->ddb_number)->toBe('ddb 0042')->and($application->application_number)->toBe('BRS-2025-2026-000001')
        ->and($application->checks()->orderBy('id')->pluck('key')->all())->toBe(array_keys(config('scholarships.checks')));
    expect($this->year->fresh()->last_application_sequence)->toBe(1);
});
it('denies reception without the receive permission', function () {
    $this->actingAs($this->staff)->post('/scholarships/applications', ['student_id' => $this->student->id])->assertForbidden();
    $this->actingAs($this->staff)->get('/scholarships/reception')->assertForbidden();
    expect(fn () => receiveFile($this, actor: $this->staff))->toThrow(AuthorizationException::class);
    expect(ScholarshipApplication::count())->toBe(0);
});
it('refuses unknown or inactive students and missing or inactive declarations', function (string $case) {
    $studentId = match ($case) {
        'unknown' => 999999,
        'inactive student' => tap($this->student)->update(['is_active' => false])->id,
        'no declaration' => Student::factory()->create()->id,
        'other year only' => tap(Student::factory()->create(), fn ($s) => StudentRecord::factory()->create(['student_id' => $s->id, 'academic_year_id' => AcademicYear::factory()->create()->id, 'status' => 'active']))->id,
        'inactive declaration' => declaredStudent($this, status: 'inactive')->id,
        'inactive level' => tap(declaredStudent($this), fn () => $this->level->update(['is_active' => false]))->id,
    };
    $this->actingAs($this->central)->post('/scholarships/applications', ['student_id' => $studentId])->assertSessionHasErrors('student_id');
    expect(ScholarshipApplication::count())->toBe(0)->and($this->year->fresh()->last_application_sequence)->toBe(0);
})->with(['unknown', 'inactive student', 'no declaration', 'other year only', 'inactive declaration', 'inactive level']);
it('selects the active declaration of the academic year among several', function () {
    $other = Establishment::factory()->create();
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $other->id, 'status' => 'inactive']);
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => AcademicYear::factory()->create()->id, 'establishment_id' => $other->id, 'status' => 'active']);
    expect(receiveFile($this)->student_record_id)->toBe($this->record->id);
});
it('numbers files uniquely per academic year and enforces it in SQL', function () {
    $first = receiveFile($this);
    $second = receiveFile($this, declaredStudent($this));
    expect($second->application_number)->toBe('BRS-2025-2026-000002');
    expect(fn () => DB::table('scholarship_applications')->where('id', $second->id)->update(['application_number' => $first->application_number]))->toThrow(QueryException::class);
});
it('allows one scholarship request per student and academic year', function () {
    $first = receiveFile($this);
    $this->actingAs($this->central)->post('/scholarships/applications', ['student_id' => $this->student->id])
        ->assertSessionHasErrors(['student_id' => 'Une seule demande de bourse par année universitaire : cet étudiant a déjà un dossier en 2025-2026 (N° DDB '.$first->reference().').']);
    $this->get('/scholarships/reception/students/'.$this->student->uuid)
        ->assertInertia(fn (Assert $page) => $page->where('student.yearApplication.reference', $first->reference()));
    $this->get('/scholarships/reception?search=RABE')
        ->assertInertia(fn (Assert $page) => $page->where('results.0.application.reference', $first->reference()));

    // A file put in the trash frees the year; restoring it while another one exists is refused.
    app(TrashManager::class)->handle($this->admin, 'applications', $first->uuid, 'trash');
    $replacement = receiveFile($this);
    expect(fn () => app(TrashManager::class)->handle($this->admin, 'applications', $first->uuid, 'restore'))->toThrow(ValidationException::class, 'déjà un autre dossier');
    expect($first->fresh()->trashed())->toBeTrue()->and($replacement->fresh()->trashed())->toBeFalse();

    // The next academic year, once current: a new request, numbered from 1 again.
    $next = AcademicYear::factory()->create(['name' => '2026-2027', 'is_active' => true]);
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $next->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id, 'status' => 'active']);
    $this->year->update(['is_current' => false]);
    $next->update(['is_current' => true]);
    $nextFile = receiveFile($this);
    expect($nextFile->academic_year_id)->toBe($next->id)->and($nextFile->ddb_number)->toBe('DDB-2026-000001');
});
it('rejects a DDB number already used in the same academic year', function () {
    receiveFile($this, ddb: 'DDB-12 34');
    expect(fn () => receiveFile($this, declaredStudent($this), 'ddb-1234'))->toThrow(ValidationException::class, 'déjà utilisé cette année universitaire');
    // Without a number written on the form, the DDB number is generated (default pattern).
    expect(receiveFile($this, declaredStudent($this))->ddb_number)->toBe('DDB-2025-000002')
        ->and(receiveFile($this, declaredStudent($this))->ddb_number)->toBe('DDB-2025-000003');
    expect(ScholarshipApplication::whereNull('ddb_key')->count())->toBe(0);
});
it('generates the DDB number from the pattern set by the platform administrator', function () {
    $this->establishment->update(['code' => 'fs']);
    $this->actingAs($this->admin)->put('/settings/platform/ddb', ['ddb_pattern' => 'umg/{etab}/{au}/{num}', 'ddb_digits' => 4])->assertSessionHasNoErrors()->assertRedirect('/settings/platform');
    expect(Activity::where('log_name', 'platform')->where('event', 'ddb_numbering_updated')->exists())->toBeTrue();
    $this->actingAs($this->central)->get('/scholarships/reception/students/'.$this->student->uuid)
        ->assertInertia(fn (Assert $page) => $page->where('nextDdb', 'UMG/FS/2025-2026/0001')
            // Shown as on the paper label: printed prefix, number on the line, label texts and QR code.
            ->where('ddbLabel.prefix', 'UMG/FS/2025-2026/')->where('ddbLabel.number', '0001')->where('ddbLabel.suffix', '')
            ->where('ddbLabel.texts.footer', 'Service Scolarité Centrale')->where('ddbLabel.qr', fn (string $qr) => str_starts_with($qr, 'data:image/svg+xml;base64,')));
    expect(receiveFile($this)->ddb_number)->toBe('UMG/FS/2025-2026/0001');
});
it('validates the DDB pattern and keeps it to the platform administrator', function () {
    $this->actingAs($this->admin)->put('/settings/platform/ddb', ['ddb_pattern' => 'DDB-{ANNEE}', 'ddb_digits' => 6])->assertSessionHasErrors('ddb_pattern');
    $this->put('/settings/platform/ddb', ['ddb_pattern' => 'DDB-{NUM}-{X}', 'ddb_digits' => 6])->assertSessionHasErrors('ddb_pattern');
    $this->put('/settings/platform/ddb', ['ddb_pattern' => 'DDB <b>{NUM}', 'ddb_digits' => 6])->assertSessionHasErrors('ddb_pattern');
    $this->put('/settings/platform/ddb', ['ddb_pattern' => 'DDB-{NUM}', 'ddb_digits' => 12])->assertSessionHasErrors('ddb_digits');
    $this->actingAs($this->central)->put('/settings/platform/ddb', ['ddb_pattern' => 'X-{NUM}', 'ddb_digits' => 3])->assertForbidden();
    expect(receiveFile($this)->ddb_number)->toBe('DDB-2025-000001');
});
it('sends the agent back to the reception search with the DDB number to copy on the label', function () {
    $response = $this->actingAs($this->central)->post('/scholarships/applications', ['student_id' => $this->student->id]);
    $application = ScholarshipApplication::sole();
    $response->assertRedirect('/scholarships/reception')
        ->assertInertiaFlash('toast.message', 'Dossier reçu : N° DDB DDB-2025-000001.');
    // No printable receipt any more.
    $this->get('/scholarships/reception/receipts/'.$application->uuid)->assertNotFound();
});
it('keeps a double click from creating two files', function () {
    $payload = ['student_id' => $this->student->id, 'ddb_number' => 'D1'];
    $this->actingAs($this->central)->post('/scholarships/applications', $payload)->assertRedirect();
    $this->post('/scholarships/applications', $payload)->assertSessionHasErrors('student_id');
    expect(ScholarshipApplication::count())->toBe(1)->and($this->year->fresh()->last_application_sequence)->toBe(1);
});
it('rechecks the declaration at reception time', function () {
    $this->actingAs($this->central)->get('/scholarships/reception?search=RABE')->assertInertia(fn (Assert $page) => $page->has('results', 1));
    $this->record->update(['status' => 'inactive']);
    $this->post('/scholarships/applications', ['student_id' => $this->student->id])->assertSessionHasErrors('student_id');
    expect(ScholarshipApplication::count())->toBe(0);
});
it('searches students on the server with a minimal preview', function (string $term) {
    declaredStudent($this, ['last_name' => 'AUTRE']);
    $response = $this->actingAs($this->central)->get('/scholarships/reception?search='.urlencode($term))
        ->assertInertia(fn (Assert $page) => $page->component('scholarships/reception')->has('results', 1)->where('results.0.id', $this->student->id)
            ->where('results.0.records.0.establishment', 'Faculté des Sciences')->where('results.0.records.0.program', 'Biologie')->missing('results.0.cin')->missing('results.0.address'));
    // The search matches on the CIN but never returns it; the typed term is only echoed back in the search box.
    expect($response->getContent())->not->toContain('Lot SECRET 7');
    if ($term !== '501012345678') {
        expect($response->getContent())->not->toContain('501012345678');
    }
})->with(['rabe', 'Hery', '501012345678', '5010 1234', '0347654321', 'hery@example.test']);
it('shows an existing file in reception search results', function () {
    $application = receiveFile($this);
    $this->actingAs($this->central)->get('/scholarships/reception?search=501012345678')
        ->assertInertia(fn (Assert $page) => $page->where('results.0.application.id', $application->id)->where('results.0.birth_date', '2003-04-05')->missing('results.0.cin'));
});

// Reception counter: full file, identity completion, agent activity
it('flags missing identity fields in reception results without their values', function () {
    $this->student->update(['mother_name' => null, 'bac_year' => null]);
    $this->actingAs($this->central)->get('/scholarships/reception?search=RABE')
        ->assertInertia(fn (Assert $page) => $page->where('results.0.uuid', $this->student->uuid)
            ->where('results.0.missing', fn ($missing) => in_array('mother_name', [...$missing], true) && in_array('bac_year', [...$missing], true) && ! in_array('cin', [...$missing], true))
            ->missing('results.0.cin')->missing('results.0.phone')->missing('student'));
});
it('opens the full student file on its own page from the reception', function () {
    $application = receiveFile($this);
    $this->actingAs($this->central)->get('/scholarships/reception/students/'.$this->student->uuid.'?search=RABE')
        ->assertInertia(fn (Assert $page) => $page->component('scholarships/reception-student')->where('student.uuid', $this->student->uuid)
            ->where('year.id', $this->year->id)->where('backUrl', '/scholarships/reception?search=RABE')->where('student.cin', '501012345678')
            ->where('student.address', 'Lot SECRET 7')->where('student.canUpdate', true)->has('student.uin')
            ->where('student.records.0.establishment', 'Faculté des Sciences')->where('student.records.0.is_reception_year', true)
            ->where('student.applications.0.reference', $application->ddb_number));
    $this->get('/scholarships/reception/students/'.Str::uuid())->assertNotFound();
    $this->actingAs($this->staff)->get('/scholarships/reception/students/'.$this->student->uuid)->assertForbidden();
});
it('opens and completes the file at the counter with the reception right, even without students.view', function () {
    Role::findByName('central_scholarship_office')->revokePermissionTo('students.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->central->fresh())->get('/scholarships/reception/students/'.$this->student->uuid)
        ->assertInertia(fn (Assert $page) => $page->where('student.uuid', $this->student->uuid)->where('student.canUpdate', true)
            ->where('student.blockReason', null)->where('student.yearApplication', null));
    $this->put('/scholarships/reception/students/'.$this->student->uuid, ['last_name' => 'RABE', 'first_names' => 'Hery', 'sex' => 'M', 'birth_date' => '2003-04-05', 'nationality' => 'Malagasy', 'bac_year' => 2021])
        ->assertSessionHasNoErrors();
    expect($this->student->fresh()->bac_year)->toBe(2021);
});
it('tells at the counter why a file cannot be received, or which one exists', function () {
    $this->record->update(['status' => 'inactive']);
    $this->actingAs($this->central)->get('/scholarships/reception/students/'.$this->student->uuid)
        ->assertInertia(fn (Assert $page) => $page->where('student.blockReason', 'Situation inactive pour l’année universitaire en cours : contactez l’établissement.'));
    $this->record->update(['status' => 'active']);
    $application = receiveFile($this);
    $this->get('/scholarships/reception/students/'.$this->student->uuid)
        ->assertInertia(fn (Assert $page) => $page->where('student.yearApplication.reference', $application->ddb_number));
});
it('lets the central office complete a student file from the reception, audited', function () {
    $this->student->update(['mother_name' => null]);
    $url = '/scholarships/reception/students/'.$this->student->uuid.'?search=RABE';
    $this->actingAs($this->central)->from($url)->put('/scholarships/reception/students/'.$this->student->uuid, [
        'last_name' => 'RABE', 'first_names' => 'Hery', 'sex' => 'M', 'birth_date' => '2003-04-05', 'nationality' => 'Malagasy',
        'cin' => '501012345678', 'phone' => '0347654321', 'email' => 'hery@example.test', 'address' => 'Lot SECRET 7', 'mother_name' => 'RASOA Marie',
    ])->assertRedirect($url)->assertSessionHasNoErrors();
    expect($this->student->fresh()->mother_name)->toBe('RASOA Marie');
    $log = Activity::where('log_name', 'students')->where('event', 'updated')->where('subject_id', $this->student->id)->latest('id')->first();
    expect($log->causer_id)->toBe($this->central->id)->and($log->properties->get('changed_fields'))->toContain('mother_name')
        ->and(json_encode($log->properties))->not->toContain('RASOA');
    $this->get('/scholarships/reception')->assertInertia(fn (Assert $page) => $page->where('activity.updatedToday', 1)
        ->where('activity.recent.0.event', 'updated')->where('activity.recent.0.fields', ['mother_name'])->where('activity.recent.0.student', 'RABE Hery'));
});
it('rejects an identity completion that breaks the identity rules', function () {
    declaredStudent($this, ['last_name' => 'AUTRE', 'phone' => '0341111111']);
    $this->actingAs($this->central)->put('/scholarships/reception/students/'.$this->student->uuid, [
        'last_name' => 'RABE', 'sex' => 'M', 'birth_date' => '2003-04-05', 'nationality' => 'Malagasy', 'phone' => '0341111111',
    ])->assertSessionHasErrors('phone');
    expect($this->student->fresh()->phone)->toBe('0347654321');
});
it('denies identity completion without students.update or reception rights', function () {
    Role::findByName('central_scholarship_office')->revokePermissionTo('students.update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $payload = ['last_name' => 'RABE', 'sex' => 'M', 'birth_date' => '2003-04-05', 'nationality' => 'Malagasy'];
    $this->actingAs($this->central->fresh())->put('/scholarships/reception/students/'.$this->student->uuid, $payload)->assertForbidden();
    // The establishment may edit its own student, but not through the reception counter.
    $this->actingAs($this->staff)->put('/scholarships/reception/students/'.$this->student->uuid, $payload)->assertForbidden();
});
it('shows the agent their own reception activity only', function () {
    $application = receiveFile($this);
    receiveFile($this, declaredStudent($this, ['last_name' => 'AUTRE']), null, $this->admin);
    $this->actingAs($this->central)->get('/scholarships/reception')
        ->assertInertia(fn (Assert $page) => $page->where('activity.today', 1)->where('activity.week', 1)->where('activity.year', 1)
            ->has('activity.days', 7)->where('activity.days.6.count', 1)
            ->has('activity.recent', 1)->where('activity.recent.0.event', 'application_received')
            ->where('activity.recent.0.reference', $application->ddb_number)->where('activity.recent.0.student', 'RABE Hery'));
});

// Isolation
it('denies students and establishments on every scholarship route', function (string $role) {
    $user = User::factory()->create()->assignRole($role);
    if ($role === 'establishment') {
        $user->forceFill(['establishment_id' => $this->establishment->id])->save();
    }
    $application = receiveFile($this);
    $this->actingAs($user);
    foreach (['/scholarships/reception', '/scholarships/applications', '/scholarships/applications/'.$application->uuid, '/scholarships/applications/export'] as $url) {
        $this->get($url)->assertForbidden();
    }
    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'rejected', 'reason' => 'Tentative non autorisée.'])->assertForbidden();
    $this->post('/scholarships/applications/'.$application->uuid.'/checks', ['key' => 'identity', 'status' => 'ok'])->assertForbidden();
    expect($application->fresh()->status)->toBe(ScholarshipApplicationStatus::Pending);
})->with(['student', 'establishment']);
it('checks the specific permission of each action whatever the URL', function () {
    $application = receiveFile($this);
    // A central account without the validate permission cannot validate, even by posting directly.
    Role::findByName('central_scholarship_office')->revokePermissionTo('scholarships.validate');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    completeChecks($application, $this->central);
    $this->actingAs($this->central)->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'validated'])->assertForbidden();
    $this->get('/scholarships/applications/'.$application->uuid)->assertInertia(fn (Assert $page) => $page->where('actions', ['needs_correction', 'rejected']));
    $this->get('/scholarships/applications/999999')->assertNotFound();
    expect($application->fresh()->status)->toBe(ScholarshipApplicationStatus::InReview);
});

// Review
it('starts the review explicitly and records actor and time', function () {
    $application = receiveFile($this);
    $this->actingAs($this->central)->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'in_review'])->assertRedirect();
    $application->refresh();
    expect($application->status)->toBe(ScholarshipApplicationStatus::InReview)->and($application->review_started_by)->toBe($this->central->id)
        ->and($application->review_started_at)->not->toBeNull()->and($application->decided_at)->toBeNull();
});
it('records checklist results and starts the review from a received file', function () {
    $application = receiveFile($this);
    $this->actingAs($this->central)->post('/scholarships/applications/'.$application->uuid.'/checks', ['key' => 'identity', 'status' => 'issue', 'note' => 'Prénom différent sur le papier'])->assertRedirect();
    $check = $application->checks()->where('key', 'identity')->sole();
    expect($check->status)->toBe(ScholarshipCheckStatus::Issue)->and($check->note)->toBe('Prénom différent sur le papier')
        ->and($check->checked_by)->toBe($this->central->id)->and($check->checked_at)->not->toBeNull()
        ->and($application->fresh()->status)->toBe(ScholarshipApplicationStatus::InReview)->and($application->fresh()->review_started_by)->toBe($this->central->id);
    $this->post('/scholarships/applications/'.$application->uuid.'/checks', ['key' => 'unknown', 'status' => 'ok'])->assertSessionHasErrors('check');
    $this->post('/scholarships/applications/'.$application->uuid.'/checks', ['key' => 'identity', 'status' => 'excellent'])->assertSessionHasErrors('status');
});

// Validation
it('validates a file only when every check is conforming', function () {
    $application = receiveFile($this);
    $this->actingAs($this->central)->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'validated'])->assertSessionHasErrors('status');
    completeChecks($application, $this->central);
    app(UpdateScholarshipCheck::class)->handle($this->central, $application, 'contact', ScholarshipCheckStatus::Issue, null);
    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'validated'])->assertSessionHasErrors('status');
    app(UpdateScholarshipCheck::class)->handle($this->central, $application, 'contact', ScholarshipCheckStatus::NotApplicable, null);
    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'validated'])->assertRedirect();
    $application->refresh();
    expect($application->status)->toBe(ScholarshipApplicationStatus::Validated)->and($application->decided_by)->toBe($this->central->id)
        ->and($application->decided_at)->not->toBeNull()->and($application->decision_reason)->toBeNull();
});
it('refuses validation when the student or declaration is no longer active', function (string $case) {
    $application = receiveFile($this);
    completeChecks($application, $this->central);
    $case === 'student' ? $this->student->update(['is_active' => false]) : $this->record->update(['status' => 'inactive']);
    expect(fn () => app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::Validated))->toThrow(ValidationException::class);
    expect($application->fresh()->status)->toBe(ScholarshipApplicationStatus::InReview);
})->with(['student', 'declaration']);
it('makes a repeated validation a safe no-op', function () {
    $application = receiveFile($this);
    completeChecks($application, $this->central);
    $action = app(TransitionScholarshipApplication::class);
    $action->handle($this->central, $application, ScholarshipApplicationStatus::Validated);
    $decidedAt = $application->fresh()->decided_at;
    $this->travel(5)->minutes();
    $action->handle($this->admin, $application, ScholarshipApplicationStatus::Validated);
    expect($application->fresh()->decided_by)->toBe($this->central->id)->and($application->fresh()->decided_at->equalTo($decidedAt))->toBeTrue()
        ->and(Activity::where('event', 'application_validated')->count())->toBe(1)
        ->and(ScholarshipApplicationStatusHistory::where('to_status', 'validated')->count())->toBe(1);
});
it('protects final decisions from any later change', function (string $final) {
    $application = receiveFile($this);
    completeChecks($application, $this->central);
    app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::from($final), $final === 'rejected' ? 'Dossier incomplet.' : null);
    foreach (['in_review', 'needs_correction', $final === 'validated' ? 'rejected' : 'validated'] as $target) {
        expect(fn () => app(TransitionScholarshipApplication::class)->handle($this->admin, $application, ScholarshipApplicationStatus::from($target), 'Tentative de réouverture.'))->toThrow(ValidationException::class);
    }
    expect(fn () => app(UpdateScholarshipCheck::class)->handle($this->admin, $application, 'identity', ScholarshipCheckStatus::Issue, null))->toThrow(ValidationException::class);
    $this->actingAs($this->central)->get('/scholarships/applications/'.$application->uuid)->assertInertia(fn (Assert $page) => $page->where('actions', [])->where('canEditChecks', false));
    expect($application->fresh()->status->value)->toBe($final);
})->with(['validated', 'rejected']);

// Correction request
it('requires a reason to request a correction and never modifies the student', function () {
    $application = receiveFile($this);
    $before = $this->student->fresh()->getAttributes();
    $this->actingAs($this->central)->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'needs_correction'])->assertSessionHasErrors('reason');
    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'needs_correction', 'reason' => 'court'])->assertSessionHasErrors('reason');
    expect(fn () => app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::NeedsCorrection, '  '))->toThrow(ValidationException::class);
    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'needs_correction', 'reason' => 'Attestation de l’établissement manquante.'])->assertRedirect();
    $application->refresh();
    expect($application->status)->toBe(ScholarshipApplicationStatus::NeedsCorrection)->and($application->decision_reason)->toBe('Attestation de l’établissement manquante.')
        ->and($application->decided_at)->toBeNull()->and($this->student->fresh()->getAttributes())->toBe($before)
        ->and(StudentCorrectionRequest::count())->toBe(0);
});
it('returns a file needing correction to review, then to a decision', function () {
    $application = receiveFile($this);
    $action = app(TransitionScholarshipApplication::class);
    $action->handle($this->central, $application, ScholarshipApplicationStatus::NeedsCorrection, 'Pièce manquante à fournir.');
    $action->handle($this->central, $application, ScholarshipApplicationStatus::InReview);
    expect($application->fresh()->decision_reason)->toBeNull();
    $action->handle($this->central, $application, ScholarshipApplicationStatus::NeedsCorrection, 'Nouvelle incohérence relevée.');
    completeChecks($application, $this->central);
    $action->handle($this->central, $application, ScholarshipApplicationStatus::Validated);
    expect($application->fresh()->status)->toBe(ScholarshipApplicationStatus::Validated);
});

// Rejection
it('requires a reason to reject and leaves the student unchanged', function () {
    $application = receiveFile($this);
    $before = $this->student->fresh()->getAttributes();
    $this->actingAs($this->central)->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'rejected'])->assertSessionHasErrors('reason');
    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'rejected', 'reason' => 'Dossier déposé hors délai.'])->assertRedirect();
    $application->refresh();
    expect($application->status)->toBe(ScholarshipApplicationStatus::Rejected)->and($application->decision_reason)->toBe('Dossier déposé hors délai.')
        ->and($application->decided_by)->toBe($this->central->id)->and($this->student->fresh()->getAttributes())->toBe($before);
});
it('keeps processing files after their academic year is no longer current', function () {
    $application = receiveFile($this);
    $this->year->update(['is_current' => false]);
    completeChecks($application, $this->central);
    app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::Validated);
    expect($application->fresh()->status)->toBe(ScholarshipApplicationStatus::Validated);
});

// Concurrency
it('serializes concurrent final decisions on the same file', function (string $first, string $second) {
    $application = receiveFile($this);
    completeChecks($application, $this->central);
    $stale = ScholarshipApplication::find($application->id);
    $reason = fn (string $s) => ScholarshipApplicationStatus::from($s)->requiresReason() ? 'Motif de décision.' : null;
    app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::from($first), $reason($first));
    expect(fn () => app(TransitionScholarshipApplication::class)->handle($this->admin, $stale, ScholarshipApplicationStatus::from($second), $reason($second)))->toThrow(ValidationException::class);
    expect($application->fresh()->status->value)->toBe($first)->and($application->fresh()->decided_by)->toBe($this->central->id);
})->with([['validated', 'rejected'], ['rejected', 'validated'], ['rejected', 'needs_correction']]);
it('lets a correction request win over a stale validation and vice versa', function () {
    $application = receiveFile($this);
    completeChecks($application, $this->central);
    $stale = ScholarshipApplication::find($application->id);
    app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::NeedsCorrection, 'Signature absente du formulaire.');
    // Validation from needs_correction is allowed, but it is now a deliberate second decision recorded in history.
    app(TransitionScholarshipApplication::class)->handle($this->admin, $stale, ScholarshipApplicationStatus::Validated);
    expect(ScholarshipApplicationStatusHistory::where('scholarship_application_id', $application->id)->pluck('to_status')->map->value->all())
        ->toBe(['pending', 'in_review', 'needs_correction', 'validated']);
    $other = receiveFile($this, declaredStudent($this));
    completeChecks($other, $this->central);
    $stale = ScholarshipApplication::find($other->id);
    app(TransitionScholarshipApplication::class)->handle($this->central, $other, ScholarshipApplicationStatus::Validated);
    expect(fn () => app(TransitionScholarshipApplication::class)->handle($this->admin, $stale, ScholarshipApplicationStatus::NeedsCorrection, 'Trop tard pour corriger.'))->toThrow(ValidationException::class);
});
it('lets two agents act on the same file without a double decision', function () {
    $application = receiveFile($this);
    $second = User::factory()->create()->assignRole('central_scholarship_office');
    app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::InReview);
    app(TransitionScholarshipApplication::class)->handle($second, ScholarshipApplication::find($application->id), ScholarshipApplicationStatus::InReview);
    expect($application->fresh()->review_started_by)->toBe($this->central->id)->and(ScholarshipApplicationStatusHistory::where('to_status', 'in_review')->count())->toBe(1);
    app(TransitionScholarshipApplication::class)->handle($second, $application, ScholarshipApplicationStatus::Rejected, 'Dossier non signé.');
    app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::Rejected, 'Rejet en double.');
    expect($application->fresh()->decided_by)->toBe($second->id)->and($application->fresh()->decision_reason)->toBe('Dossier non signé.')
        ->and(Activity::where('event', 'application_rejected')->count())->toBe(1);
});
// Audit and history
it('audits receptions, reviews, checks and decisions without personal data', function () {
    $validated = receiveFile($this, ddb: 'DDB-9');
    completeChecks($validated, $this->central);
    app(TransitionScholarshipApplication::class)->handle($this->central, $validated, ScholarshipApplicationStatus::Validated);
    $rejected = receiveFile($this, declaredStudent($this, ['cin' => '509999999999', 'phone' => '0320000001', 'email' => 'autre@example.test', 'address' => 'Lot AUTRE 9']));
    app(TransitionScholarshipApplication::class)->handle($this->central, $rejected, ScholarshipApplicationStatus::NeedsCorrection, 'Le téléphone 0347654321 diffère.');
    app(TransitionScholarshipApplication::class)->handle($this->central, $rejected, ScholarshipApplicationStatus::Rejected, 'Refus confidentiel détaillé.');
    $this->actingAs($this->central)->get('/scholarships/applications/export')->assertOk();

    $events = Activity::where('log_name', 'scholarships')->orderBy('id')->pluck('event')->all();
    expect($events)->toContain('application_received', 'review_started', 'check_updated', 'application_validated', 'application_needs_correction', 'application_rejected', 'applications_exported');
    $received = Activity::where('event', 'application_received')->where('subject_id', $validated->id)->sole();
    // MySQL JSON columns do not keep key order: compare as a map.
    expect($received->causer_id)->toBe($this->central->id)->and($received->properties->only(['application_id', 'academic_year_id', 'student_id', 'status'])->all())
        ->toEqual(['application_id' => $validated->id, 'academic_year_id' => $this->year->id, 'student_id' => $this->student->id, 'status' => 'pending']);
    expect(Activity::where('event', 'check_updated')->first()->properties->get('check_key'))->toBe('identity');
    $json = Activity::all()->toJson();
    foreach (['501012345678', '0347654321', 'hery@example.test', 'Lot SECRET 7', '2003-04-05', '509999999999', 'Lot AUTRE 9', 'Refus confidentiel', 'RABE'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});
it('keeps a readable business history of every transition', function () {
    $application = receiveFile($this);
    app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::InReview);
    app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::NeedsCorrection, 'Copie du BAC illisible.');
    app(TransitionScholarshipApplication::class)->handle($this->admin, $application, ScholarshipApplicationStatus::Rejected, 'Aucune réponse de l’étudiant.');
    $history = ScholarshipApplicationStatusHistory::where('scholarship_application_id', $application->id)->orderBy('id')->get();
    expect($history->map(fn ($h) => [$h->from_status?->value, $h->to_status->value, $h->actor_id, $h->reason])->all())->toBe([
        [null, 'pending', $this->central->id, null],
        ['pending', 'in_review', $this->central->id, null],
        ['in_review', 'needs_correction', $this->central->id, 'Copie du BAC illisible.'],
        ['needs_correction', 'rejected', $this->admin->id, 'Aucune réponse de l’étudiant.'],
    ]);
    $this->actingAs($this->central)->get('/scholarships/applications/'.$application->uuid)->assertInertia(fn (Assert $page) => $page->has('application.history', 4)->where('application.history.3.reason', 'Aucune réponse de l’étudiant.'));
    expect(fn () => DB::table('scholarship_application_status_histories')->insert(['scholarship_application_id' => 999999, 'to_status' => 'pending', 'actor_id' => $this->central->id, 'created_at' => now()]))->toThrow(QueryException::class);
});

// List, export and performance
it('lists, filters and searches files with server pagination', function () {
    $mine = receiveFile($this, ddb: 'DDB-777');
    $other = Establishment::factory()->create(['name' => 'École Normale']);
    $foreignStudent = Student::factory()->create(['last_name' => 'ZAFY', 'cin' => '509000000001']);
    StudentRecord::factory()->create(['student_id' => $foreignStudent->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $other->id, 'level_id' => $this->level->id, 'program_id' => null, 'status' => 'active']);
    $foreign = receiveFile($this, $foreignStudent);
    app(TransitionScholarshipApplication::class)->handle($this->central, $foreign, ScholarshipApplicationStatus::Rejected, 'Dossier hors délai.');
    $this->actingAs($this->central);
    $count = fn (string $query) => $this->get('/scholarships/applications'.$query)->assertOk()->viewData('page')['props']['applications']['total'];
    expect($count(''))->toBe(2)->and($count('?establishment_id='.$other->id))->toBe(1)->and($count('?status=rejected'))->toBe(1)
        ->and($count('?search=ddb-777'))->toBe(1)->and($count('?search=DDB 777'))->toBe(0)->and($count('?search='.$mine->application_number))->toBe(1)->and($count('?search=50900'))->toBe(1)->and($count('?search=509.000'))->toBe(1)
        ->and($count('?search=zaf'))->toBe(1)->and($count('?academic_year_id='.$this->year->id.'&level_id='.$this->level->id))->toBe(2)
        ->and($count('?received_from='.now()->addDay()->format('Y-m-d')))->toBe(0);
    $this->get('/scholarships/applications?received_from=2026-02-01&received_to=2026-01-01')->assertSessionHasErrors('received_to');
});
it('counts files per status for the current filters, ignoring the status filter itself', function () {
    receiveFile($this);
    $other = Establishment::factory()->create(['name' => 'École Normale']);
    $foreignStudent = Student::factory()->create(['last_name' => 'ZAFY']);
    StudentRecord::factory()->create(['student_id' => $foreignStudent->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $other->id, 'level_id' => $this->level->id, 'program_id' => null, 'status' => 'active']);
    app(TransitionScholarshipApplication::class)->handle($this->central, receiveFile($this, $foreignStudent), ScholarshipApplicationStatus::Rejected, 'Dossier hors délai.');
    app(TransitionScholarshipApplication::class)->handle($this->central, receiveFile($this, declaredStudent($this)), ScholarshipApplicationStatus::InReview, null);
    $this->actingAs($this->central);
    $empty = ['awaiting_list' => 0, 'pending' => 0, 'in_review' => 0, 'needs_correction' => 0, 'validated' => 0, 'rejected' => 0];
    $this->get('/scholarships/applications?status=rejected')->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)
        ->where('statusCounts', [...$empty, 'pending' => 1, 'in_review' => 1, 'rejected' => 1]));
    $this->get('/scholarships/applications?status=pending&establishment_id='.$this->establishment->id)->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)
        ->where('statusCounts', [...$empty, 'pending' => 1, 'in_review' => 1]));
    $this->get('/scholarships/applications?search=zaf')->assertInertia(fn (Assert $page) => $page->where('statusCounts', [...$empty, 'rejected' => 1]));
});
it('shows a received file as « En attente de la liste » until the establishment list confirms the situation', function () {
    $this->record->update(['source' => 'central', 'verification' => 'pending', 'status' => 'pending']);
    $application = receiveFile($this);
    receiveFile($this, declaredStudent($this));
    $this->actingAs($this->central)->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page
        ->where('statusCounts.awaiting_list', 1)->where('statusCounts.pending', 1)
        ->where('applications.data.1.status', 'awaiting_list')->where('applications.data.1.status_label', 'En attente de la liste'));
    $this->get('/scholarships/applications?status=awaiting_list')->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)->where('applications.data.0.uuid', $application->uuid));
    $this->get('/scholarships/applications?status=pending')->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)->whereNot('applications.data.0.uuid', $application->uuid));
    $this->get('/scholarships/applications/'.$application->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('application.status', 'pending')->where('application.shown_status', ['value' => 'awaiting_list', 'label' => 'En attente de la liste']));

    // Once the list is imported and confirms the situation, the file is simply « Reçu ».
    $this->record->update(['verification' => 'verified', 'status' => 'active']);
    $this->get('/scholarships/applications?status=pending')->assertInertia(fn (Assert $page) => $page->where('applications.total', 2)->where('statusCounts.awaiting_list', 0));
});
it('paginates the file list by 25', function () {
    foreach (range(1, 27) as $i) {
        receiveFile($this, declaredStudent($this));
    }
    $this->actingAs($this->central)->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page->has('applications.data', 25)->where('applications.total', 27));
    $this->get('/scholarships/applications?page=2')->assertInertia(fn (Assert $page) => $page->has('applications.data', 2));
});
it('loads the file list with a constant number of queries', function () {
    $this->actingAs($this->central);
    receiveFile($this);
    $queries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/scholarships/applications')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $queries(); // warm the permission cache, which is data-independent
    $few = $queries();
    foreach (range(1, 12) as $i) {
        receiveFile($this, declaredStudent($this, [], 'active'));
    }
    expect($queries())->toBe($few);
});
it('gives each throttled action its own counter, so processing files never blocks the export', function () {
    $application = receiveFile($this);
    $this->actingAs($this->central);
    foreach (range(1, 6) as $i) {
        foreach (['identity', 'enrollment'] as $key) {
            $this->post('/scholarships/applications/'.$application->uuid.'/checks', ['key' => $key, 'status' => $i % 2 ? 'ok' : 'issue'])->assertRedirect();
        }
    }
    $this->get('/scholarships/applications/export')->assertOk();
});
it('exports filtered files to Excel without sensitive data and requires the export permission', function () {
    receiveFile($this, ddb: '=HYPERLINK("x")');
    $rejected = receiveFile($this, declaredStudent($this, ['last_name' => 'REJETE']));
    app(TransitionScholarshipApplication::class)->handle($this->central, $rejected, ScholarshipApplicationStatus::Rejected, 'Motif interne détaillé.');
    $response = $this->actingAs($this->central)->get('/scholarships/applications/export?status=pending')->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx')->and($response->headers->get('cache-control'))->toContain('no-store');
    $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    $rows = $sheet->toArray();
    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['N° DDB', 'Date de naissance', 'Nom', 'Prénoms', 'Établissement', 'Niveau', 'Parcours', 'Année universitaire', 'Statut', 'Date de réception', 'Date de décision'])
        ->and(array_slice($rows[1], 0, 8))->toBe(['=HYPERLINK("x")', '05/04/2003', 'RABE', 'Hery', 'Faculté des Sciences', 'Licence 1', 'Biologie', '2025-2026'])
        // Written as text, never as a formula.
        ->and($sheet->getCell('A2')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getFreezePane())->toBe('A2');
    $flat = json_encode($rows);
    expect($flat)->not->toContain('REJETE')->not->toContain('501012345678')->not->toContain('0347654321')->not->toContain('hery@example.test')->not->toContain('Lot SECRET 7')->not->toContain('Motif interne');
    Role::findByName('central_scholarship_office')->revokePermissionTo('scholarships.export');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->get('/scholarships/applications/export')->assertForbidden();
    $this->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page->where('canExport', false));
});
it('shows the scholarship menu only to authorized accounts', function () {
    $this->actingAs($this->central)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.scholarships', ['receive' => true, 'labels' => false, 'applications' => true]));
    $this->actingAs($this->staff)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.scholarships', ['receive' => false, 'labels' => false, 'applications' => false]));
    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.scholarships.labels', true));
});
it('renders the file detail with official data, checklist and allowed actions', function () {
    $application = receiveFile($this, ddb: 'DDB-1');
    $this->actingAs($this->central)->get('/scholarships/applications/'.$application->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page->component('scholarships/applications/show')
        ->where('application.student.establishment', 'Faculté des Sciences')->where('application.student.program', 'Biologie')->has('application.checks', 5)
        ->where('actions', ['in_review', 'validated', 'needs_correction', 'rejected'])->where('canEditChecks', true)->where('canViewStudent', true)
        ->where('application.student.birth_date', '2003-04-05')->where('application.student.sex', 'M')->where('application.student.nationality', 'Malagasy')
        ->missing('application.student.cin')->missing('application.student.address')->missing('application.student.phone'));
});
it('identifies the student of a listed file by names, birth date and CIN, never by contacts', function () {
    $this->establishment->update(['code' => 'FS']);
    $this->level->update(['code' => 'L1']);
    receiveFile($this);
    $this->actingAs($this->central)->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page
        ->where('applications.data.0.student', ['last_name' => 'RABE', 'first_names' => 'Hery', 'cin' => '501012345678', 'birth_date' => '2003-04-05'])
        ->where('applications.data.0.establishment', ['code' => 'FS', 'name' => 'Faculté des Sciences'])
        ->where('applications.data.0.level', ['code' => 'L1', 'name' => 'Licence 1'])
        ->missing('applications.data.0.campaign')->missing('applications.data.0.student.phone')->missing('applications.data.0.student.email'));
});

it('offers on each listed file the decisions the account may take, and stays on the list after one', function () {
    $application = receiveFile($this);
    $this->actingAs($this->central)->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page
        ->where('applications.data.0.actions', ['in_review', 'validated', 'needs_correction', 'rejected'])
        ->where('bulkActions', ['in_review', 'validated', 'needs_correction', 'rejected']));
    $this->from('/scholarships/applications?status=pending')
        ->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'in_review', 'from_list' => true])
        ->assertRedirect('/scholarships/applications?status=pending');
    expect($application->fresh()->status)->toBe(ScholarshipApplicationStatus::InReview);
    // Without the list flag, the file page as before.
    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'needs_correction', 'reason' => 'Relevé de notes manquant.'])
        ->assertRedirect('/scholarships/applications/'.$application->uuid);

    Role::findByName('central_scholarship_office')->revokePermissionTo('scholarships.validate');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->central->fresh())->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page
        ->where('applications.data.0.actions', ['in_review', 'rejected'])->where('bulkActions', ['in_review', 'needs_correction', 'rejected']));
});
it('applies one decision to a selection, file by file, and reports the refused ones', function () {
    $ready = receiveFile($this);
    completeChecks($ready, $this->central);
    $unchecked = receiveFile($this, declaredStudent($this, ['last_name' => 'SANS CONTROLE']));
    $issue = receiveFile($this, declaredStudent($this, ['last_name' => 'NON CONFORME']));
    app(UpdateScholarshipCheck::class)->handle($this->central, $issue, 'identity', ScholarshipCheckStatus::Issue, 'Nom différent.');
    $done = receiveFile($this, declaredStudent($this, ['last_name' => 'DEJA']));
    completeChecks($done, $this->central);
    app(TransitionScholarshipApplication::class)->handle($this->central, $done, ScholarshipApplicationStatus::Validated);

    $this->actingAs($this->central)->from('/scholarships/applications')->post('/scholarships/applications/bulk', [
        'status' => 'validated', 'applications' => [$ready->uuid, $unchecked->uuid, $issue->uuid, $done->uuid],
    ])->assertRedirect('/scholarships/applications')
        ->assertInertiaFlash('toast.message', '2 dossier(s) : validé · 1 déjà dans cet état · 1 refusé(s) (droits, contrôles ou situation non vérifiée).');
    // Quick validation: the checks still to do are recorded as conforming by the agent, through « En vérification ».
    expect($ready->fresh()->status)->toBe(ScholarshipApplicationStatus::Validated)
        ->and($unchecked->fresh()->status)->toBe(ScholarshipApplicationStatus::Validated)
        ->and($unchecked->checks()->pluck('status')->map->value->unique()->all())->toBe(['ok'])
        ->and($unchecked->checks()->first()->checked_by)->toBe($this->central->id)
        ->and(ScholarshipApplicationStatusHistory::query()->where('scholarship_application_id', $unchecked->id)->orderBy('id')->pluck('to_status')->map->value->all())->toBe(['pending', 'in_review', 'validated'])
        // A non-conforming check still blocks, and nothing of the file is changed.
        ->and($issue->fresh()->status)->toBe(ScholarshipApplicationStatus::InReview)
        ->and($issue->checks()->where('status', 'ok')->count())->toBe(0)
        ->and(ScholarshipApplicationStatusHistory::query()->where('scholarship_application_id', $ready->id)->where('to_status', 'validated')->count())->toBe(1);
    $unchecked = $issue;

    // A reason is required to reject, and the same reason is kept on each file.
    $this->post('/scholarships/applications/bulk', ['status' => 'rejected', 'applications' => [$unchecked->uuid]])->assertSessionHasErrors('reason');
    $this->post('/scholarships/applications/bulk', ['status' => 'rejected', 'applications' => [$unchecked->uuid], 'reason' => 'Dossier hors délai de dépôt.'])->assertSessionHasNoErrors();
    expect($unchecked->fresh())->status->toBe(ScholarshipApplicationStatus::Rejected)->decision_reason->toBe('Dossier hors délai de dépôt.');
});
it('keeps bulk decisions to the accounts that may read the files', function () {
    $application = receiveFile($this);
    $this->actingAs($this->staff)->post('/scholarships/applications/bulk', ['status' => 'in_review', 'applications' => [$application->uuid]])->assertForbidden();
    Role::findByName('central_scholarship_office')->revokePermissionTo('scholarships.review');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->central->fresh())->post('/scholarships/applications/bulk', ['status' => 'in_review', 'applications' => [$application->uuid]])
        ->assertInertiaFlash('toast.type', 'warning');
    expect($application->fresh()->status)->toBe(ScholarshipApplicationStatus::Pending);
});

it('explains in plain words why a file cannot be validated yet', function () {
    $this->record->update(['source' => 'central', 'verification' => 'pending', 'status' => 'pending']);
    $application = receiveFile($this);
    $pending = 'La situation de l’étudiant est en attente de la liste de Faculté des Sciences : le dossier pourra être validé une fois cette liste importée.';
    $this->actingAs($this->central)->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page
        // The list validates quickly (checks still to do are confirmed): only the situation blocks there.
        ->where('applications.data.0.validationBlockers', [$pending]));
    $this->get('/scholarships/applications/'.$application->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('validationBlockers', [$pending, '5 contrôles sur 5 restent à faire ou non conformes : ouvrez le dossier pour les compléter.']));
    // Refused from the list too, and the checks are left untouched.
    $this->from('/scholarships/applications')->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'validated', 'from_list' => true])->assertSessionHasErrors('status');
    expect($application->checks()->where('status', 'ok')->count())->toBe(0)->and($application->fresh()->status)->toBe(ScholarshipApplicationStatus::Pending);
    expect(fn () => app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::Validated))
        ->toThrow(ValidationException::class, 'Validation impossible. '.$pending);

    // Situation confirmed by the list and checks done: nothing left, « Valider » is open.
    $this->record->update(['verification' => 'verified', 'status' => 'active']);
    completeChecks($application, $this->central);
    $this->get('/scholarships/applications/'.$application->uuid)->assertInertia(fn (Assert $page) => $page->where('validationBlockers', []));
});

it('receives the file of a pending central entry but validates it only once the establishment list confirmed it', function () {
    $this->record->update(['source' => 'central', 'verification' => 'pending', 'status' => 'pending']);
    $application = receiveFile($this);
    completeChecks($application, $this->central);
    $this->actingAs($this->central)->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'validated'])->assertSessionHasErrors('status');
    $this->record->update(['verification' => 'matched']);
    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'validated'])->assertSessionHasErrors('status');
    $this->record->update(['verification' => 'verified', 'status' => 'active']);
    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'validated'])->assertRedirect();
    expect($application->fresh()->status)->toBe(ScholarshipApplicationStatus::Validated);
});
