<?php

use App\Actions\Corrections\CancelStudentCorrection;
use App\Actions\Corrections\RequestStudentCorrection;
use App\Actions\Corrections\ReviewStudentCorrection;
use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->bourse = 'http://'.config('portals.bourse_domain');
    $this->a = Establishment::factory()->create(['name' => 'Établissement A']);
    $this->b = Establishment::factory()->create(['name' => 'Établissement B']);
    $this->year = AcademicYear::factory()->create(['is_current' => true, 'is_active' => true]);
    $this->student = Student::factory()->create([
        'last_name' => 'RAKOTO', 'first_names' => 'Jean', 'sex' => 'M', 'birth_date' => '2001-02-03', 'mother_name' => 'RASOA Marie',
        'cin' => '401011012345', 'nationality' => 'Malagasy', 'phone' => '0341234567', 'email' => 'officiel@example.test', 'address' => 'Lot II A 12', 'bac_year' => 2019,
    ]);
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'status' => 'active']);
    $this->studentUser = User::factory()->create(['email' => 'login@example.test'])->assignRole('student');
    $this->studentUser->forceFill(['student_id' => $this->student->id])->save();
    $this->staffA = User::factory()->create()->assignRole('establishment');
    $this->staffA->forceFill(['establishment_id' => $this->a->id])->save();
    $this->staffB = User::factory()->create()->assignRole('establishment');
    $this->staffB->forceFill(['establishment_id' => $this->b->id])->save();
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

function asCorrectionStudent(TestCase $test, ?User $user = null): TestCase
{
    return $test->actingAs($user ?? $test->studentUser, 'student')->withSession(['student_portal' => true]);
}
function makeCorrection(TestCase $test, StudentCorrectionField $field = StudentCorrectionField::Phone, string $value = '0381234567', ?User $user = null): StudentCorrectionRequest
{
    return app(RequestStudentCorrection::class)->handle($user ?? $test->studentUser, $field, $value, null);
}
function otherLinkedStudent(TestCase $test, ?Establishment $establishment = null): array
{
    $student = Student::factory()->create(['phone' => '0330000000']);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $test->year->id, 'establishment_id' => ($establishment ?? $test->b)->id, 'status' => 'active']);
    $user = User::factory()->create()->assignRole('student');
    $user->forceFill(['student_id' => $student->id])->save();

    return [$student, $user];
}

// Student API
it('lets a linked student request a correction without touching official data', function () {
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'phone', 'value' => '038 12 345 67', 'message' => 'Nouveau numéro'])
        ->assertCreated()->assertJsonPath('correction.status', 'pending')->assertJsonPath('correction.proposed_value', '0381234567');
    $correction = StudentCorrectionRequest::sole();
    expect($correction->student_id)->toBe($this->student->id)->and($correction->user_id)->toBe($this->studentUser->id)
        ->and($correction->field)->toBe(StudentCorrectionField::Phone)->and($this->student->fresh()->phone)->toBe('0341234567');
    expect(DB::table('student_correction_requests')->value('proposed_value'))->not->toContain('0381234567');
});
it('refuses unlinked, inactive, unauthenticated and internal accounts', function () {
    $this->postJson($this->bourse.'/api/student/corrections', ['field' => 'phone', 'value' => '0381234567'])->assertUnauthorized();
    $unlinked = User::factory()->create()->assignRole('student');
    asCorrectionStudent($this, $unlinked)->postJson($this->bourse.'/api/student/corrections', ['field' => 'phone', 'value' => '0381234567'])->assertForbidden();
    asCorrectionStudent($this, $unlinked)->getJson($this->bourse.'/api/student/corrections')->assertForbidden();
    $this->student->update(['is_active' => false]);
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'phone', 'value' => '0381234567'])->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->actingAs($this->admin, 'web')->postJson($this->bourse.'/api/student/corrections', ['field' => 'phone', 'value' => '0381234567'])->assertUnauthorized();
    expect(StudentCorrectionRequest::count())->toBe(0);
});
it('rejects fields outside the whitelist', function (string $field) {
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => $field, 'value' => 'X'])->assertUnprocessable()->assertJsonValidationErrors('field');
    expect(StudentCorrectionRequest::count())->toBe(0);
})->with(['student_number', 'birth_place', 'bac_registration_number', 'repeat_code', 'scholarship_request_reference', 'is_active', 'establishment_id', 'level_id', 'status', 'id', 'password']);
it('validates the proposed value with the manual-entry rules', function (string $field, string $value) {
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => $field, 'value' => $value])->assertUnprocessable()->assertJsonValidationErrors('value');
    expect(StudentCorrectionRequest::count())->toBe(0);
})->with([
    ['phone', '034-ABC'], ['email', 'not-an-email'], ['birth_date', '03/02/2001'], ['birth_date', '2999-01-01'], ['bac_year', '1800'], ['bac_year', 'deux-mille'],
    ['cin_issued_at', 'hier'], ['last_name', '   '], ['address', str_repeat('a', 2001)], ['sex', 'X'], ['nationality', 'Martienne'], ['bac_series', 'Z9'],
]);
it('rejects a value identical to the official one after normalization', function (string $field, string $value) {
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => $field, 'value' => $value])->assertUnprocessable()->assertJsonValidationErrors('value');
})->with([['phone', '034 12 345 67'], ['phone', '+261 34 12 345 67'], ['email', 'OFFICIEL@example.test'], ['cin', '4010 1101 2345'], ['birth_date', '2001-02-03'], ['bac_year', '2019'], ['last_name', ' RAKOTO '], ['sex', 'masculin'], ['nationality', 'MALAGASY'], ['mother_name', ' RASOA  Marie ']]);
it('always targets the linked student and ignores any student id in the payload', function () {
    [$other] = otherLinkedStudent($this);
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'phone', 'value' => '0381234567', 'student_id' => $other->id])->assertCreated();
    expect(StudentCorrectionRequest::sole()->student_id)->toBe($this->student->id);
});
it('allows a single pending request per field, in the application and in SQL', function () {
    $first = makeCorrection($this);
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'phone', 'value' => '0329999999'])->assertUnprocessable()->assertJsonValidationErrors('field');
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'address', 'value' => 'Lot III'])->assertCreated();
    expect(fn () => DB::table('student_correction_requests')->insert(['uuid' => (string) Str::uuid7(), 'student_id' => $this->student->id, 'user_id' => $this->studentUser->id, 'field' => 'phone', 'proposed_value' => 'x', 'current_fingerprint' => str_repeat('0', 64), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
    app(CancelStudentCorrection::class)->handle($this->studentUser, $first);
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'phone', 'value' => '0329999999'])->assertCreated();
});
it('lets the student cancel only their own pending request', function () {
    $correction = makeCorrection($this);
    [, $otherUser] = otherLinkedStudent($this);
    $foreign = makeCorrection($this, user: $otherUser);
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections/'.$foreign->uuid.'/cancel', [])->assertNotFound();
    expect($foreign->fresh()->status)->toBe(StudentCorrectionStatus::Pending);
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections/'.$correction->uuid.'/cancel', [])->assertOk()->assertJsonPath('correction.status', 'cancelled');
    expect($correction->fresh()->status)->toBe(StudentCorrectionStatus::Cancelled);
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections/'.$correction->uuid.'/cancel', [])->assertOk();
});
it('does not let a student cancel a decided request', function (bool $approve) {
    $correction = makeCorrection($this);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, $approve, $approve ? null : 'Justificatif manquant.');
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections/'.$correction->uuid.'/cancel', [])->assertUnprocessable();
    expect($correction->fresh()->status)->toBe($approve ? StudentCorrectionStatus::Approved : StudentCorrectionStatus::Rejected);
})->with(['approved' => true, 'rejected' => false]);
it('lists only the student own requests without internal data', function () {
    makeCorrection($this, StudentCorrectionField::Cin, '401011099999');
    $rejected = makeCorrection($this);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $rejected, false, 'Numéro non justifié par un document.');
    [, $otherUser] = otherLinkedStudent($this);
    makeCorrection($this, StudentCorrectionField::Address, 'SECRET OTHER ADDRESS', $otherUser);
    $response = asCorrectionStudent($this)->getJson($this->bourse.'/api/student/corrections')->assertOk()->assertJsonCount(2, 'corrections')
        ->assertJsonPath('corrections.0.status_label', 'Refusée')->assertJsonPath('corrections.0.decision_reason', 'Numéro non justifié par un document.')
        ->assertJsonPath('corrections.1.proposed_value', '•••••••••999');
    foreach (['reviewed_by', 'user_id', 'student_id', 'current_fingerprint', 'pending_marker'] as $key) {
        $response->assertJsonMissingPath('corrections.0.'.$key);
    }
    expect($response->getContent())->not->toContain('SECRET OTHER ADDRESS')->not->toContain($this->staffA->name)->not->toContain('401011099999');
});
it('rate limits correction requests per account', function () {
    $fields = ['last_name', 'first_names', 'mother_name', 'cin_issued_place', 'address', 'bac_series', 'nationality', 'phone', 'email', 'sex'];
    foreach ($fields as $i => $field) {
        $value = match ($field) {
            'phone' => '0389999999', 'email' => 'nouveau@example.test', 'bac_series' => 'C', 'nationality' => 'Comorien', 'sex' => 'F', default => 'Valeur '.$i
        };
        asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => $field, 'value' => $value])->assertCreated();
    }
    asCorrectionStudent($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'birth_date', 'value' => '2001-02-04'])->assertTooManyRequests();
});

// Establishment scope
it('scopes the internal list and review to the current active declaration of the establishment', function () {
    $mine = makeCorrection($this);
    [, $otherUser] = otherLinkedStudent($this);
    $foreign = makeCorrection($this, user: $otherUser);
    $this->actingAs($this->staffA, 'web')->get('/corrections')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('corrections/index')->has('corrections.data', 1)->where('corrections.data.0.id', $mine->id)->where('corrections.data.0.establishments.0', 'Établissement A')
            ->where('corrections.data.0.student.birth_date', '2001-02-03')->missing('corrections.data.0.student.cin'));
    $this->get('/corrections/'.$foreign->uuid)->assertForbidden();
    $this->post('/corrections/'.$foreign->uuid.'/review', ['decision' => 'approve'])->assertForbidden();
    $this->get('/corrections?establishment_id='.$this->b->id)->assertForbidden();
    expect($foreign->fresh()->status)->toBe(StudentCorrectionStatus::Pending);
});
it('denies an establishment whose declaration is inactive or from another year', function (string $case) {
    $correction = makeCorrection($this);
    $record = StudentRecord::query()->where('student_id', $this->student->id)->sole();
    if ($case === 'inactive') {
        $record->update(['status' => 'inactive']);
    } else {
        $this->year->update(['is_current' => false]);
        AcademicYear::factory()->create(['is_current' => true, 'is_active' => true]);
    }
    $this->actingAs($this->staffA, 'web')->get('/corrections')->assertInertia(fn (Assert $page) => $page->has('corrections.data', 0));
    $this->get('/corrections/'.$correction->uuid)->assertForbidden();
    $this->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'approve'])->assertForbidden();
    expect($correction->fresh()->status)->toBe(StudentCorrectionStatus::Pending);
})->with(['inactive', 'past year']);
it('keeps an identity shared with another establishment read-only for the establishment', function () {
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'establishment_id' => $this->b->id, 'status' => 'inactive']);
    $correction = makeCorrection($this);
    $this->actingAs($this->staffA, 'web')->get('/corrections/'.$correction->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReview', false));
    $this->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($this->admin, 'web')->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'approve'])->assertRedirect();
    expect($this->student->fresh()->phone)->toBe('0381234567');
});
it('never exposes values in the internal list', function () {
    makeCorrection($this, StudentCorrectionField::Cin, '401011099999');
    $response = $this->actingAs($this->central, 'web')->get('/corrections')->assertOk();
    expect($response->getContent())->not->toContain('401011099999')->not->toContain('401011012345')->not->toContain('0341234567')->not->toContain('Lot II A 12');
});

// Central office and other roles
it('gives the central office global read-only access', function () {
    $mine = makeCorrection($this);
    [, $otherUser] = otherLinkedStudent($this);
    makeCorrection($this, user: $otherUser);
    $this->actingAs($this->central, 'web')->get('/corrections')->assertInertia(fn (Assert $page) => $page->has('corrections.data', 2));
    $this->get('/corrections?establishment_id='.$this->b->id)->assertInertia(fn (Assert $page) => $page->has('corrections.data', 1));
    $this->get('/corrections/'.$mine->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReview', false)->where('correction.current_value', '0341234567')->where('correction.proposed_value', '0381234567'));
    $this->post('/corrections/'.$mine->uuid.'/review', ['decision' => 'approve'])->assertForbidden();
    expect(fn () => app(ReviewStudentCorrection::class)->handle($this->central, $mine, true, null))->toThrow(AuthorizationException::class);
    expect($this->student->fresh()->phone)->toBe('0341234567');
});
it('refuses student accounts on the internal correction screens', function () {
    $correction = makeCorrection($this);
    $this->actingAs($this->studentUser, 'web')->get('/corrections')->assertForbidden();
    $this->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'approve'])->assertForbidden();
});
it('shows the menu entry only to authorized internal accounts', function () {
    $this->actingAs($this->central, 'web')->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.canViewCorrections', true));
    $this->actingAs(User::factory()->create()->assignRole('developer'), 'web')->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.canViewCorrections', true));
});

// Approval
it('approves by changing only the requested field', function () {
    $before = $this->student->fresh()->getAttributes();
    $correction = makeCorrection($this);
    $this->actingAs($this->staffA, 'web')->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'approve'])->assertRedirect('/corrections/'.$correction->uuid);
    $after = $this->student->fresh()->getAttributes();
    expect($after['phone'])->toBe('0381234567');
    unset($before['phone'], $before['updated_at'], $after['phone'], $after['updated_at']);
    expect($after)->toBe($before);
    $correction->refresh();
    expect($correction->status)->toBe(StudentCorrectionStatus::Approved)->and($correction->reviewed_by)->toBe($this->staffA->id)
        ->and($correction->reviewed_at)->not->toBeNull()->and($correction->rejection_reason)->toBeNull();
});
it('applies dates and years with their native types', function () {
    $date = makeCorrection($this, StudentCorrectionField::BirthDate, '2001-02-04');
    $year = makeCorrection($this, StudentCorrectionField::BacYear, '2020');
    app(ReviewStudentCorrection::class)->handle($this->staffA, $date, true, null);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $year, true, null);
    $student = $this->student->fresh();
    expect($student->birth_date->format('Y-m-d'))->toBe('2001-02-04')->and($student->bac_year)->toBe(2020);
});
it('makes a repeated approval a safe no-op', function () {
    $correction = makeCorrection($this);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, true, null);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, true, null);
    $this->actingAs($this->staffA, 'web')->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'approve'])->assertRedirect();
    expect(Activity::where('event', 'correction_approved')->count())->toBe(1);
});
it('refuses a correction that would duplicate another identity', function (string $field, string $value) {
    Student::factory()->create(['cin' => '401011077777', 'phone' => '0341077777', 'email' => 'autre@example.test']);
    $correction = makeCorrection($this, StudentCorrectionField::from($field), $value);
    expect(fn () => app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, true, null))->toThrow(ValidationException::class);
    expect($correction->fresh()->status)->toBe(StudentCorrectionStatus::Pending)->and($this->student->fresh()->getAttribute($field))->not->toBe($value);
})->with([['cin', '401011077777'], ['phone', '0341077777'], ['email', 'autre@example.test']]);
it('refuses a CIN issue date earlier than the birth date', function () {
    $correction = makeCorrection($this, StudentCorrectionField::CinIssuedAt, '1999-01-01');
    expect(fn () => app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, true, null))->toThrow(ValidationException::class);
    expect($this->student->fresh()->cin_issued_at)->toBeNull();
});
it('blocks approval when the official value changed since the request', function () {
    $correction = makeCorrection($this);
    $this->student->update(['phone' => '0320000000']);
    $this->actingAs($this->staffA, 'web')->get('/corrections/'.$correction->uuid)->assertInertia(fn (Assert $page) => $page->where('correction.conflict', true));
    $this->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'approve'])->assertSessionHasErrors('correction');
    expect($this->student->fresh()->phone)->toBe('0320000000')->and($correction->fresh()->status)->toBe(StudentCorrectionStatus::Pending);
    $this->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'reject', 'rejection_reason' => 'Valeur déjà mise à jour.'])->assertRedirect();
    expect($correction->fresh()->status)->toBe(StudentCorrectionStatus::Rejected);
});
it('refuses approval for an inactive student', function () {
    $correction = makeCorrection($this);
    $this->student->update(['is_active' => false]);
    expect(fn () => app(ReviewStudentCorrection::class)->handle($this->admin, $correction, true, null))->toThrow(ValidationException::class);
    expect($correction->fresh()->status)->toBe(StudentCorrectionStatus::Pending);
});
it('rolls back the identity change and the audit when the decision cannot be saved', function () {
    $correction = makeCorrection($this);
    $activities = Activity::count();
    StudentCorrectionRequest::updating(function (StudentCorrectionRequest $model) {
        if ($model->status === StudentCorrectionStatus::Approved) {
            throw new RuntimeException('simulated failure');
        }
    });
    expect(fn () => app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, true, null))->toThrow(RuntimeException::class);
    expect($this->student->fresh()->phone)->toBe('0341234567')->and($correction->fresh()->status)->toBe(StudentCorrectionStatus::Pending)
        ->and(Activity::count())->toBe($activities);
});

// Rejection
it('requires a reason to reject and leaves the student unchanged', function () {
    $correction = makeCorrection($this);
    $this->actingAs($this->staffA, 'web')->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'reject'])->assertSessionHasErrors('rejection_reason');
    $this->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'reject', 'rejection_reason' => 'court'])->assertSessionHasErrors('rejection_reason');
    expect(fn () => app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, false, ' '))->toThrow(ValidationException::class);
    expect($correction->fresh()->status)->toBe(StudentCorrectionStatus::Pending);
    $this->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'reject', 'rejection_reason' => 'Aucun justificatif fourni.'])->assertRedirect();
    $correction->refresh();
    expect($correction->status)->toBe(StudentCorrectionStatus::Rejected)->and($correction->rejection_reason)->toBe('Aucun justificatif fourni.')
        ->and($correction->reviewed_by)->toBe($this->staffA->id)->and($this->student->fresh()->phone)->toBe('0341234567');
});

// Email
it('never changes the login email when the official contact email is corrected', function () {
    $correction = makeCorrection($this, StudentCorrectionField::Email, 'Nouveau@Example.test');
    app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, true, null);
    expect($this->student->fresh()->email)->toBe('nouveau@example.test')->and($this->studentUser->fresh()->email)->toBe('login@example.test')
        ->and($this->studentUser->fresh()->email_verified_at)->not->toBeNull();
});

// Audit
it('audits request, cancellation, approval and rejection with field and ids only', function () {
    $approved = makeCorrection($this);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $approved, true, null);
    $rejected = makeCorrection($this, StudentCorrectionField::Cin, '401011099999');
    app(ReviewStudentCorrection::class)->handle($this->staffA, $rejected, false, 'Pièce illisible fournie.');
    $cancelled = makeCorrection($this, StudentCorrectionField::Address, 'Lot IV B 99');
    app(CancelStudentCorrection::class)->handle($this->studentUser, $cancelled);
    $birth = makeCorrection($this, StudentCorrectionField::BirthDate, '2001-02-05');
    app(ReviewStudentCorrection::class)->handle($this->admin, $birth, true, null);
    $email = makeCorrection($this, StudentCorrectionField::Email, 'autre@example.test');
    app(ReviewStudentCorrection::class)->handle($this->staffA, $email, true, null);

    $log = Activity::where('log_name', 'student_corrections')->orderBy('id')->get();
    expect($log->pluck('event')->all())->toBe(['correction_requested', 'correction_approved', 'correction_requested', 'correction_rejected', 'correction_requested', 'correction_cancelled', 'correction_requested', 'correction_approved', 'correction_requested', 'correction_approved']);
    $approval = $log->firstWhere('event', 'correction_approved');
    expect($approval->causer_id)->toBe($this->staffA->id)->and($approval->properties->all())->toEqual(['field' => 'phone', 'student_id' => $this->student->id, 'correction_id' => $approved->id]);
    expect($log->firstWhere('event', 'correction_cancelled')->causer_id)->toBe($this->studentUser->id);
    expect(Activity::where('log_name', 'students')->latest('id')->first()->properties->get('changed_fields'))->toBe(['email']);
    $json = Activity::all()->toJson();
    foreach (['0341234567', '0381234567', '401011012345', '401011099999', 'Lot II A 12', 'Lot IV B 99', '2001-02-03', '2001-02-05', 'officiel@example.test', 'autre@example.test', 'Pièce illisible'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});

// Concurrency
it('serializes conflicting decisions on the same request', function () {
    $correction = makeCorrection($this);
    $stale = StudentCorrectionRequest::find($correction->id);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $correction, true, null);
    expect(fn () => app(ReviewStudentCorrection::class)->handle($this->admin, $stale, false, 'Refus concurrent tardif.'))->toThrow(ValidationException::class);
    expect($correction->fresh()->status)->toBe(StudentCorrectionStatus::Approved)->and($this->student->fresh()->phone)->toBe('0381234567');

    $other = makeCorrection($this, StudentCorrectionField::Address, 'Lot V');
    $stale = StudentCorrectionRequest::find($other->id);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $other, false, 'Adresse non justifiée.');
    expect(fn () => app(ReviewStudentCorrection::class)->handle($this->admin, $stale, true, null))->toThrow(ValidationException::class);
    expect($this->student->fresh()->address)->toBe('Lot II A 12');
});
it('serializes student cancellation and staff approval', function () {
    $correction = makeCorrection($this);
    $stale = StudentCorrectionRequest::find($correction->id);
    app(CancelStudentCorrection::class)->handle($this->studentUser, $correction);
    expect(fn () => app(ReviewStudentCorrection::class)->handle($this->staffA, $stale, true, null))->toThrow(ValidationException::class);
    expect($this->student->fresh()->phone)->toBe('0341234567');

    $next = makeCorrection($this);
    $stale = StudentCorrectionRequest::find($next->id);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $next, true, null);
    expect(fn () => app(CancelStudentCorrection::class)->handle($this->studentUser, $stale))->toThrow(ValidationException::class);
    expect($next->fresh()->status)->toBe(StudentCorrectionStatus::Approved);
});
it('makes a second request for the same field depend on the first decision', function () {
    $first = makeCorrection($this);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $first, true, null);
    $second = makeCorrection($this, StudentCorrectionField::Phone, '0331111111');
    app(ReviewStudentCorrection::class)->handle($this->staffA, $second, true, null);
    expect($this->student->fresh()->phone)->toBe('0331111111');
    $third = makeCorrection($this, StudentCorrectionField::Phone, '0342222222');
    $fourth = makeCorrection($this, StudentCorrectionField::Address, 'Lot VI');
    app(ReviewStudentCorrection::class)->handle($this->staffA, $fourth, true, null);
    app(ReviewStudentCorrection::class)->handle($this->staffA, $third, true, null);
    expect($this->student->fresh()->only(['phone', 'address']))->toBe(['phone' => '0342222222', 'address' => 'Lot VI']);
});
it('lets only one of two establishments review a request', function () {
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => AcademicYear::factory()->create(['is_current' => false])->id, 'establishment_id' => $this->b->id, 'status' => 'active']);
    $correction = makeCorrection($this);
    $this->actingAs($this->staffB, 'web')->post('/corrections/'.$correction->uuid.'/review', ['decision' => 'reject', 'rejection_reason' => 'Refus par un autre établissement.'])->assertForbidden();
    expect($correction->fresh()->status)->toBe(StudentCorrectionStatus::Pending);
});
