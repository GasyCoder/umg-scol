<?php

use App\Actions\Corrections\CancelStudentCorrection;
use App\Actions\Corrections\RequestStudentCorrection;
use App\Actions\Corrections\ReviewStudentCorrection;
use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Actions\Scholarships\TransitionScholarshipApplication;
use App\Actions\Scholarships\UpdateScholarshipCheck;
use App\Actions\StudentPortal\ConfirmStudentInformation;
use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCheckStatus;
use App\Enums\StudentCorrectionField;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentInformationConfirmation;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentConfirmationContext;
use App\Support\StudentInformationFingerprint;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->bourse = 'http://'.config('portals.bourse_domain');
    $this->a = Establishment::factory()->create(['name' => 'Établissement A']);
    $this->b = Establishment::factory()->create(['name' => 'Établissement B']);
    $this->year = AcademicYear::factory()->create(['name' => '2026-2027', 'is_current' => true, 'is_active' => true]);
    $this->level = Level::factory()->create(['name' => 'L1']);
    $this->program = Program::factory()->create(['establishment_id' => $this->a->id, 'name' => 'Informatique']);
    $this->student = Student::factory()->create([
        'last_name' => 'RABE', 'first_names' => 'Aina', 'sex' => 'F', 'birth_date' => '2002-03-04', 'mother_name' => 'RASOA Mahajanga', 'nationality' => 'Malagasy',
        'cin' => '401011099999', 'phone' => '0341112233', 'email' => 'contact@example.test', 'address' => 'Lot 12 Tsaramandroso',
        'bac_series' => 'C', 'bac_year' => 2020,
    ]);
    $this->record = StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'level_id' => $this->level->id, 'program_id' => $this->program->id, 'status' => 'active']);
    $this->studentUser = User::factory()->create(['email' => 'login@example.test'])->assignRole('student');
    $this->studentUser->forceFill(['student_id' => $this->student->id])->save();
    $this->staffA = User::factory()->create()->assignRole('establishment');
    $this->staffA->forceFill(['establishment_id' => $this->a->id])->save();
    $this->staffB = User::factory()->create()->assignRole('establishment');
    $this->staffB->forceFill(['establishment_id' => $this->b->id])->save();
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

function asConfirmingStudent(TestCase $test, ?User $user = null): TestCase
{
    // The application survives between requests of a test: drop the user cached by the Sanctum guard.
    app('auth')->forgetGuards();

    return $test->actingAs($user ?? $test->studentUser, 'student')->withSession(['student_portal' => true, 'student_identity_verified' => ($user ?? $test->studentUser)->student_id]);
}
function confirmationState(TestCase $test, ?User $user = null): array
{
    return asConfirmingStudent($test, $user)->getJson($test->bourse.'/api/student/confirmation')->assertOk()->json('confirmation');
}
function confirmInformation(TestCase $test, ?User $user = null, ?string $version = null, array $extra = []): TestResponse
{
    $version ??= confirmationState($test, $user)['version'];

    return asConfirmingStudent($test, $user)->postJson($test->bourse.'/api/student/confirmation', ['version' => $version, ...$extra]);
}
function linkedConfirmationStudent(TestCase $test, Establishment $establishment): array
{
    $student = Student::factory()->create();
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $test->year->id, 'establishment_id' => $establishment->id, 'status' => 'active']);
    $user = User::factory()->create()->assignRole('student');
    $user->forceFill(['student_id' => $student->id])->save();

    return [$student, $user];
}
function currentFingerprint(Student $student): ?string
{
    return StudentConfirmationContext::load($student->fresh())->fingerprint();
}

// API
it('shows the confirmation status to a linked student', function () {
    expect(confirmationState($this))->toMatchArray(['status' => 'never_confirmed', 'confirmed_at' => null, 'academic_year' => '2026-2027', 'can_confirm' => true, 'reason' => null])
        ->and(confirmationState($this)['version'])->toHaveLength(64);
    asConfirmingStudent($this)->getJson($this->bourse.'/api/student/me')->assertOk()->assertJsonPath('confirmation.status', 'never_confirmed');
});
it('refuses unauthenticated, unlinked, internal and inactive accounts', function () {
    $this->getJson($this->bourse.'/api/student/confirmation')->assertUnauthorized();
    $this->postJson($this->bourse.'/api/student/confirmation', ['version' => str_repeat('a', 64)])->assertUnauthorized();
    $unlinked = User::factory()->create()->assignRole('student');
    asConfirmingStudent($this, $unlinked)->getJson($this->bourse.'/api/student/confirmation')->assertForbidden();
    asConfirmingStudent($this, $unlinked)->postJson($this->bourse.'/api/student/confirmation', ['version' => str_repeat('a', 64)])->assertForbidden();
    $this->actingAs($this->admin, 'student')->withSession(['student_portal' => true])->getJson($this->bourse.'/api/student/confirmation')->assertForbidden();
    expect(StudentInformationConfirmation::count())->toBe(0);
});
it('never shows or uses the confirmation of another student', function () {
    [$other, $otherUser] = linkedConfirmationStudent($this, $this->b);
    confirmInformation($this, $otherUser)->assertOk();
    expect(confirmationState($this)['status'])->toBe('never_confirmed');
    // The token of another student's version is useless for this account.
    confirmInformation($this, null, confirmationState($this, $otherUser)['version'])->assertUnprocessable()->assertJsonValidationErrors('version');
    expect(StudentInformationConfirmation::where('student_id', $this->student->id)->count())->toBe(0);
});
it('ignores any student, record, year or data payload sent by the client', function () {
    [$other] = linkedConfirmationStudent($this, $this->b);
    confirmInformation($this, null, null, ['student_id' => $other->id, 'student_record_id' => 999, 'academic_year_id' => 999, 'last_name' => 'FAUX', 'email' => 'faux@example.test'])->assertOk();
    $confirmation = StudentInformationConfirmation::sole();
    expect($confirmation->student_id)->toBe($this->student->id)->and($confirmation->student_record_id)->toBe($this->record->id)->and($confirmation->academic_year_id)->toBe($this->year->id)
        ->and($confirmation->information_fingerprint)->toBe(currentFingerprint($this->student))
        ->and($this->student->fresh()->last_name)->toBe('RABE')->and($this->student->fresh()->email)->toBe('contact@example.test');
    asConfirmingStudent($this)->postJson($this->bourse.'/api/student/confirmation', [])->assertUnprocessable()->assertJsonValidationErrors('version');
});

// Confirmation
it('records a first confirmation with a server fingerprint, date, record and year', function () {
    $this->freezeTime();
    $before = [DB::table('students')->where('id', $this->student->id)->value('updated_at'), DB::table('student_records')->where('id', $this->record->id)->value('updated_at')];
    confirmInformation($this)->assertOk()->assertJsonPath('confirmation.status', 'confirmed')->assertJsonPath('confirmation.confirmed_at', now()->toIso8601String());
    $confirmation = StudentInformationConfirmation::sole();
    expect($confirmation->user_id)->toBe($this->studentUser->id)->and($confirmation->sequence)->toBe(1)->and($confirmation->confirmed_at->toIso8601String())->toBe(now()->toIso8601String())
        ->and($confirmation->information_fingerprint)->toBe(StudentInformationFingerprint::compute($this->student->fresh(), $this->record->fresh()->load(['academicYear', 'establishment', 'level', 'program'])))
        ->and($confirmation->information_fingerprint)->toMatch('/^[0-9a-f]{64}$/');
    expect(confirmationState($this))->toMatchArray(['status' => 'confirmed', 'can_confirm' => true]);
    // Nothing in the official data changed.
    expect([DB::table('students')->where('id', $this->student->id)->value('updated_at'), DB::table('student_records')->where('id', $this->record->id)->value('updated_at')])->toBe($before);
});
it('is idempotent on a double click or retry of the same version', function () {
    $version = confirmationState($this)['version'];
    confirmInformation($this, null, $version)->assertOk();
    confirmInformation($this, null, $version)->assertOk()->assertJsonPath('confirmation.status', 'confirmed');
    confirmInformation($this)->assertOk();
    expect(StudentInformationConfirmation::count())->toBe(1)->and(Activity::where('event', 'student_information_confirmed')->count())->toBe(1);
});
it('audits the confirmation with identifiers only', function () {
    confirmInformation($this)->assertOk();
    $confirmation = StudentInformationConfirmation::sole();
    $log = Activity::where('event', 'student_information_confirmed')->sole();
    expect($log->log_name)->toBe('student_confirmations')->and($log->causer_id)->toBe($this->studentUser->id)
        ->and($log->properties->all())->toEqual(['user_id' => $this->studentUser->id, 'student_id' => $this->student->id, 'student_record_id' => $this->record->id, 'academic_year_id' => $this->year->id, 'confirmation_id' => $confirmation->id]);
});

// Prerequisites
it('refuses an inactive student', function () {
    $version = confirmationState($this)['version'];
    $this->student->update(['is_active' => false]);
    asConfirmingStudent($this)->getJson($this->bourse.'/api/student/confirmation')->assertForbidden();
    confirmInformation($this, null, $version)->assertForbidden();
    expect(StudentInformationConfirmation::count())->toBe(0);
});
it('refuses when there is no current academic year', function () {
    $this->year->update(['is_current' => false]);
    expect(confirmationState($this))->toMatchArray(['can_confirm' => false, 'reason' => 'no_current_year', 'version' => null]);
    confirmInformation($this, null, str_repeat('a', 64))->assertUnprocessable()->assertJsonValidationErrors(['confirmation' => 'Aucune année universitaire courante']);
    expect(StudentInformationConfirmation::count())->toBe(0);
});
it('refuses when the student has no declaration for the current year', function () {
    $previous = AcademicYear::factory()->create(['is_current' => false]);
    $this->record->update(['academic_year_id' => $previous->id]);
    expect(confirmationState($this))->toMatchArray(['can_confirm' => false, 'reason' => 'no_current_record']);
    confirmInformation($this, null, str_repeat('a', 64))->assertUnprocessable()->assertJsonValidationErrors('confirmation');
    expect(StudentInformationConfirmation::count())->toBe(0);
});
it('refuses when the current declaration is inactive', function () {
    $version = confirmationState($this)['version'];
    $this->record->update(['status' => 'inactive']);
    expect(confirmationState($this))->toMatchArray(['can_confirm' => false, 'reason' => 'inactive_record']);
    confirmInformation($this, null, $version)->assertUnprocessable()->assertJsonValidationErrors(['confirmation' => 'inactive']);
    expect(StudentInformationConfirmation::count())->toBe(0);
});
it('refuses an ambiguous situation instead of guessing', function () {
    // Legacy data without the one-active-declaration guarantee, simulated by dropping the index inside the test
    // transaction; only SQLite rolls DDL back (MySQL enforces the index, so the case cannot arise there).
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Transactional DDL required.');
    }
    DB::statement('DROP INDEX student_records_one_active_year');
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->b->id, 'status' => 'active']);
    expect(confirmationState($this))->toMatchArray(['can_confirm' => false, 'reason' => 'ambiguous', 'version' => null]);
    confirmInformation($this, null, str_repeat('a', 64))->assertUnprocessable()->assertJsonValidationErrors('confirmation');
    expect(StudentInformationConfirmation::count())->toBe(0);
});

// Corrections
it('blocks the confirmation while a correction is pending', function () {
    $version = confirmationState($this)['version'];
    app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::MotherName, 'RAZAFY Lalao', null);
    expect(confirmationState($this))->toMatchArray(['can_confirm' => false, 'reason' => 'pending_corrections', 'version' => null, 'message' => 'Une ou plusieurs corrections sont encore en attente. Vous pourrez confirmer vos informations après leur traitement ou leur annulation.']);
    confirmInformation($this, null, $version)->assertUnprocessable()->assertJsonValidationErrors(['confirmation' => 'Une ou plusieurs corrections sont encore en attente.']);
    expect(StudentInformationConfirmation::count())->toBe(0);
});
it('allows the confirmation after a cancelled correction', function () {
    $correction = app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::MotherName, 'RAZAFY Lalao', null);
    app(CancelStudentCorrection::class)->handle($this->studentUser, $correction);
    confirmInformation($this)->assertOk()->assertJsonPath('confirmation.status', 'confirmed');
});
it('allows a new confirmation after an approved correction, which made the previous one stale', function () {
    confirmInformation($this)->assertOk();
    $correction = app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::MotherName, 'RAZAFY Lalao', null);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, true, null);
    expect(confirmationState($this))->toMatchArray(['status' => 'stale', 'can_confirm' => true]);
    confirmInformation($this)->assertOk()->assertJsonPath('confirmation.status', 'confirmed');
    expect(StudentInformationConfirmation::orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2]);
});
it('allows the confirmation after a rejected correction', function () {
    $correction = app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::MotherName, 'RAZAFY Lalao', null);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, false, 'Le numéro actuel est le bon.');
    confirmInformation($this)->assertOk()->assertJsonPath('confirmation.status', 'confirmed');
});

// Stale detection, deduced from the fingerprint only
it('becomes stale when a displayed field changes', function (string $field, mixed $value) {
    confirmInformation($this)->assertOk();
    $this->student->update([$field => $value]);
    expect(confirmationState($this))->toMatchArray(['status' => 'stale', 'can_confirm' => true])
        ->and(StudentInformationConfirmation::sole()->wasChanged())->toBeFalse();
})->with([
    'identity' => ['last_name', 'RABEMANANJARA'],
    'birth date' => ['birth_date', '2002-03-05'],
    'phone' => ['phone', '0329998877'],
    'contact email' => ['email', 'autre@example.test'],
    'address' => ['address', 'Lot 13 Tsaramandroso'],
    'sex' => ['sex', 'M'],
    'mother name' => ['mother_name', 'RASOA Vola'],
    'nationality' => ['nationality', 'Comorien'],
    'bac series' => ['bac_series', 'D'],
    'bac year' => ['bac_year', 2019],
]);
it('stays confirmed when only hidden CIN data or the login email changes', function () {
    confirmInformation($this)->assertOk();
    $this->student->update(['cin' => '401011088888', 'cin_issued_place' => 'Antananarivo']);
    $this->studentUser->update(['email' => 'new-login@example.test']);
    expect(confirmationState($this)['status'])->toBe('confirmed')->and($this->student->fresh()->email)->toBe('contact@example.test');
});
it('becomes stale when the level of the current declaration changes', function () {
    confirmInformation($this)->assertOk();
    $this->record->update(['level_id' => Level::factory()->create()->id]);
    expect(confirmationState($this)['status'])->toBe('stale');
});
it('becomes stale when the repeat code of the current declaration changes', function () {
    confirmInformation($this)->assertOk();
    $this->record->update(['repeat_code' => 'R']);
    expect(confirmationState($this)['status'])->toBe('stale');
});
it('becomes stale when the mention of the displayed program is renamed', function () {
    $mention = Mention::factory()->create(['establishment_id' => $this->a->id, 'name' => 'Mathématiques et informatique']);
    $this->program->update(['mention_id' => $mention->id]);
    confirmInformation($this)->assertOk();
    $mention->update(['name' => 'Sciences du numérique']);
    expect(confirmationState($this)['status'])->toBe('stale');
});
it('stays confirmed when only the scholarship request deposit of the declaration changes', function () {
    confirmInformation($this)->assertOk();
    $this->record->update(['scholarship_requested_at' => '2026-10-01', 'scholarship_request_reference' => 'DB-001']);
    expect(confirmationState($this)['status'])->toBe('confirmed');
});
it('becomes stale when the program changes, including a rename of the displayed program', function () {
    confirmInformation($this)->assertOk();
    $this->program->update(['name' => 'Informatique appliquée']);
    expect(confirmationState($this)['status'])->toBe('stale');
    confirmInformation($this)->assertOk();
    $this->record->update(['program_id' => Program::factory()->create(['establishment_id' => $this->a->id])->id]);
    expect(confirmationState($this)['status'])->toBe('stale');
    confirmInformation($this)->assertOk();
    $this->record->update(['program_id' => null]);
    expect(confirmationState($this)['status'])->toBe('stale');
});
it('does not carry a confirmation over to a new current academic year', function () {
    confirmInformation($this)->assertOk();
    $this->year->update(['is_current' => false]);
    $next = AcademicYear::factory()->create(['name' => '2027-2028', 'is_current' => true, 'is_active' => true]);
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $next->id, 'establishment_id' => $this->a->id, 'level_id' => $this->level->id, 'status' => 'active']);
    expect(confirmationState($this))->toMatchArray(['status' => 'stale', 'academic_year' => '2027-2028', 'can_confirm' => true]);
    confirmInformation($this)->assertOk()->assertJsonPath('confirmation.status', 'confirmed');
    expect(StudentInformationConfirmation::orderBy('sequence')->pluck('academic_year_id')->all())->toBe([$this->year->id, $next->id]);
});
it('treats a return to an earlier version as needing a new confirmation and keeps the history', function () {
    confirmInformation($this)->assertOk();
    $this->student->update(['phone' => '0329998877']);
    confirmInformation($this)->assertOk();
    $this->student->update(['phone' => '0341112233']);
    expect(confirmationState($this)['status'])->toBe('stale');
    confirmInformation($this)->assertOk();
    expect(StudentInformationConfirmation::count())->toBe(3)->and(confirmationState($this)['status'])->toBe('confirmed');
});
it('makes every confirmation stale when APP_KEY rotates', function () {
    confirmInformation($this)->assertOk();
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    expect(confirmationState($this)['status'])->toBe('stale');
});

// Security
it('never exposes the fingerprint through the API', function () {
    confirmInformation($this)->assertOk();
    $fingerprint = StudentInformationConfirmation::sole()->information_fingerprint;
    $responses = [
        asConfirmingStudent($this)->getJson($this->bourse.'/api/student/confirmation')->getContent(),
        asConfirmingStudent($this)->getJson($this->bourse.'/api/student/me')->getContent(),
        confirmInformation($this)->getContent(),
    ];
    foreach ($responses as $content) {
        expect($content)->not->toContain($fingerprint)->not->toContain('information_fingerprint');
    }
    expect(StudentInformationConfirmation::sole()->toArray())->not->toHaveKey('information_fingerprint');
});
it('keeps the fingerprint and personal data out of the activity log', function () {
    confirmInformation($this)->assertOk();
    $json = Activity::where('log_name', 'student_confirmations')->get()->toJson();
    foreach ([StudentInformationConfirmation::sole()->information_fingerprint, 'RABE', 'Aina', '2002-03-04', 'Mahajanga', '0341112233', 'contact@example.test', 'Tsaramandroso', 'RASOA', '401011099999'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});
it('confirms only the version that was displayed', function () {
    $displayed = confirmationState($this)['version'];
    $this->student->update(['address' => 'Lot 99 Mahabibo']);
    confirmInformation($this, null, $displayed)->assertUnprocessable()->assertJsonValidationErrors(['version' => 'Vos informations ont changé']);
    expect(StudentInformationConfirmation::count())->toBe(0);
    confirmInformation($this)->assertOk();
});
it('allows students only, never staff, to confirm', function () {
    foreach ([$this->staffA, $this->central, $this->admin] as $staff) {
        $this->actingAs($staff)->post('http://'.config('portals.scol_domain').'/api/student/confirmation')->assertNotFound();
    }
    expect(fn () => app(ConfirmStudentInformation::class)->handle($this->admin, str_repeat('a', 64)))->toThrow(HttpException::class);
});

// Internal page
it('shows the confirmation state to an establishment for its own student', function () {
    $scol = 'http://'.config('portals.scol_domain');
    $this->actingAs($this->staffA)->get($scol.'/students/'.$this->student->uuid)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('confirmation.status', 'never_confirmed')->where('confirmation.confirmed_at', null));
    confirmInformation($this)->assertOk();
    $this->actingAs($this->staffA)->get($scol.'/students/'.$this->student->uuid)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('confirmation.status', 'confirmed')->where('confirmation.academic_year', '2026-2027')->missing('confirmation.information_fingerprint')->missing('confirmation.version'));
    $this->student->update(['phone' => '0329998877']);
    $this->actingAs($this->staffA)->get($scol.'/students/'.$this->student->uuid)->assertInertia(fn (Assert $page) => $page->where('confirmation.status', 'stale'));
});
it('hides the state of students outside the establishment scope', function () {
    confirmInformation($this)->assertOk();
    $this->actingAs($this->staffB)->get('http://'.config('portals.scol_domain').'/students/'.$this->student->uuid)->assertForbidden();
});
it('shows the state globally to the central office', function () {
    [$other, $otherUser] = linkedConfirmationStudent($this, $this->b);
    confirmInformation($this, $otherUser)->assertOk();
    $this->actingAs($this->central)->get('http://'.config('portals.scol_domain').'/students/'.$other->uuid)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('confirmation.status', 'confirmed'));
});

// Concurrency
it('keeps a single current version when two confirmations race', function () {
    $version = confirmationState($this)['version'];
    $fingerprint = currentFingerprint($this->student);
    // A concurrent request takes sequence 1 just before this insert: the unique key rejects the duplicate and
    // the action replays once instead of failing. (The committed winner itself is exercised on MySQL.)
    $raced = false;
    StudentInformationConfirmation::creating(function (StudentInformationConfirmation $confirmation) use ($fingerprint, &$raced) {
        if (! $raced) {
            $raced = true;
            DB::table('student_information_confirmations')->insert(['student_id' => $confirmation->student_id, 'sequence' => 1, 'user_id' => $confirmation->user_id, 'student_record_id' => $confirmation->student_record_id, 'academic_year_id' => $confirmation->academic_year_id, 'information_fingerprint' => $fingerprint, 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    });
    confirmInformation($this, null, $version)->assertOk()->assertJsonPath('confirmation.status', 'confirmed');
    expect(StudentInformationConfirmation::count())->toBe(1);
});
it('never stores a fingerprint other than the locked state, even if data changed after display', function () {
    $version = confirmationState($this)['version'];
    StudentInformationConfirmation::creating(fn (StudentInformationConfirmation $confirmation) => expect($confirmation->information_fingerprint)->toBe(currentFingerprint($this->student)));
    $this->record->update(['level_id' => Level::factory()->create()->id]);
    confirmInformation($this, null, $version)->assertUnprocessable()->assertJsonValidationErrors('version');
    confirmInformation($this)->assertOk();
    expect(StudentInformationConfirmation::sole()->information_fingerprint)->toBe(currentFingerprint($this->student));
});
it('never confirms alongside a correction created concurrently', function () {
    $version = confirmationState($this)['version'];
    app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::Address, 'Lot 14 Tsaramandroso', null);
    expect(fn () => app(ConfirmStudentInformation::class)->handle($this->studentUser, $version))->toThrow(ValidationException::class);
    expect(StudentInformationConfirmation::count())->toBe(0);
});
it('never blocks the paper scholarship workflow for a student without account or confirmation', function () {
    $student = Student::factory()->create();
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'level_id' => $this->level->id, 'status' => 'active']);
    $application = app(ReceiveScholarshipApplication::class)->handle($this->central, $student->id, null, null);
    foreach (array_keys((array) config('scholarships.checks')) as $key) {
        app(UpdateScholarshipCheck::class)->handle($this->central, $application, $key, ScholarshipCheckStatus::Ok, null);
    }
    app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::Validated);
    expect($application->fresh()->status)->toBe(ScholarshipApplicationStatus::Validated)->and(StudentInformationConfirmation::count())->toBe(0)
        ->and(StudentConfirmationContext::load($student)->status())->toBe('never_confirmed');
});
