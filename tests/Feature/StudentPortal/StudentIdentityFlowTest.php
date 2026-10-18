<?php

use App\Actions\StudentPortal\LinkStudent;
use App\Mail\StudentOtpMail;
use App\Models\EmailOtpChallenge;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentIdentityQuestions;
use App\Support\Trash\TrashManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    $this->base = 'http://'.config('portals.bourse_domain').'/api/student';
    $this->student = Student::factory()->create(['email' => 'aina@example.test', 'birth_date' => '2002-03-04', 'cin' => '401001001001', 'phone' => '0341112233', 'mother_name' => 'RASOA Marie']);
    $this->user = User::factory()->create(['email' => $this->student->email])->assignRole('student');
});

function identitySession(TestCase $test): TestCase
{
    return $test->actingAs($test->user, 'student')->withSession(['student_portal' => true]);
}

it('sends no code and creates no account for unknown inactive trashed or ambiguous official emails', function (string $case) {
    if ($case === 'unknown') {
        $email = 'unknown@example.test';
    } else {
        $email = $this->student->email;
    }
    if ($case === 'inactive') {
        $this->student->update(['is_active' => false]);
    }
    if ($case === 'ambiguous') {
        Student::factory()->create(['email' => $email]);
    }
    if ($case === 'trashed') {
        $admin = User::factory()->create()->assignRole('superadmin');
        app(TrashManager::class)->handle($admin, 'students', $this->student->uuid, 'trash');
    }
    $before = User::count();
    $this->postJson($this->base.'/auth/otp/request', ['email' => $email])->assertUnprocessable()->assertJsonValidationErrors('email');
    expect(EmailOtpChallenge::count())->toBe(0)->and(User::count())->toBe($before);
    Mail::assertNothingQueued();
})->with(['unknown', 'inactive', 'trashed', 'ambiguous']);

it('rechecks the official email when the OTP is verified', function () {
    $id = $this->postJson($this->base.'/auth/otp/request', ['email' => $this->student->email])->assertOk()->json('challenge_id');
    $code = Mail::queued(StudentOtpMail::class)->sole()->code;
    $this->student->update(['email' => 'changed@example.test']);
    $this->postJson($this->base.'/auth/otp/verify', ['challenge_id' => $id, 'code' => $code])->assertUnprocessable();
    $this->assertGuest('student');
});

it('checks birth date on the server and never reveals document values', function () {
    identitySession($this);
    $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2002-03-05'])->assertUnprocessable()->assertJsonValidationErrors('birth_date');
    $this->postJson($this->base.'/link', ['answers' => ['q1' => '401001001001'], 'birth_date' => '2002-03-04'])->assertUnprocessable();
    $response = $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2002-03-04'])->assertOk()->assertJsonPath('verified', true)
        ->assertJsonCount(1, 'questions')->assertJsonPath('questions.0.id', 'q1')->assertJsonPath('questions.0.label', 'Numéro de votre CIN');
    expect($response->getContent())->not->toContain('401001001001', '0341112233', 'RASOA Marie', '2002-03-04');
    expect(session('student_birth_verified'))->not->toHaveKeys(['birth_date', 'cin', 'phone']);
});

it('requires both verification steps before opening an already linked record', function () {
    $this->user->forceFill(['student_id' => $this->student->id])->save();
    identitySession($this);
    $this->getJson($this->base.'/session')->assertJsonPath('linked', false);
    $this->getJson($this->base.'/me')->assertForbidden();
    $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2002-03-04'])->assertOk();
    $this->getJson($this->base.'/me')->assertForbidden();
    $this->postJson($this->base.'/link', ['answers' => ['q1' => 'wrong'], 'birth_date' => '2002-03-04'])->assertOk()->assertJsonPath('linked', false)->assertJsonPath('remaining', 4);
    $this->getJson($this->base.'/me')->assertForbidden();
    $this->postJson($this->base.'/link', ['answers' => ['q1' => '401001001001'], 'birth_date' => '2002-03-04'])->assertOk()->assertJsonPath('linked', true);
    $this->getJson($this->base.'/session')->assertJsonPath('linked', true);
    $this->getJson($this->base.'/me')->assertOk()->assertJsonPath('student.email', 'aina@example.test');
});

it('asks one recorded proof without CIN and accepts a normalized mother name', function () {
    $this->student->update(['cin' => null, 'phone' => null, 'mother_name' => 'RASOA Marie']);
    identitySession($this);
    $response = $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2002-03-04'])->assertOk()->assertJsonCount(1, 'questions');
    expect($response->json('questions.0.label'))->toBe('Nom et prénoms de votre mère');
    $this->postJson($this->base.'/link', ['answers' => ['q1' => 'rasoa   marie'], 'birth_date' => '2002-03-04'])->assertOk()->assertJsonPath('linked', true);
    expect($this->user->fresh()->student_id)->toBe($this->student->id);
});

it('never selects someone else’s record even when its identifier and birth date are known', function () {
    $other = Student::factory()->create(['email' => 'other@example.test', 'cin' => '401001001002', 'birth_date' => '2002-03-04']);
    StudentIdentityQuestions::issue($this->user, $this->student);
    expect(app(LinkStudent::class)->handle($this->user, ['q1' => $other->cin], '2002-03-04'))->toBeFalse()
        ->and($this->user->fresh()->student_id)->toBeNull();
});

it('expires the birth date proof before the final identity check', function () {
    identitySession($this);
    $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2002-03-04'])->assertOk();
    $this->travel(11)->minutes();
    $this->postJson($this->base.'/link', ['answers' => ['q1' => '401001001001'], 'birth_date' => '2002-03-04'])->assertUnprocessable()->assertJsonValidationErrors('birth_date');
    expect($this->user->fresh()->student_id)->toBeNull();
});

it('accepts only the recorded PNES or pre-registration reference without CIN', function (string $type) {
    $this->student->update(['cin' => null, 'phone' => null, 'mother_name' => null, 'scholarship_code' => $type === 'scholarship_code' ? 'PNES20260042' : null]);
    if ($type === 'preinscription_reference') {
        StudentRecord::factory()->create(['student_id' => $this->student->id, 'preinscription_reference' => 'PRE-2026-0042']);
    }
    identitySession($this);
    $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2002-03-04'])->assertOk()->assertJsonCount(1, 'questions');
    $this->postJson($this->base.'/link', ['answers' => ['q1' => 'UNKNOWN'], 'birth_date' => '2002-03-04'])->assertOk()->assertJsonPath('linked', false);
    $value = $type === 'scholarship_code' ? 'PNES 2026 0042' : 'PRE-2026-0042';
    $this->postJson($this->base.'/link', ['answers' => ['q1' => $value], 'birth_date' => '2002-03-04'])->assertOk()->assertJsonPath('linked', true);
})->with(['scholarship_code', 'preinscription_reference']);

it('clears a previous identity proof on a new OTP login even for a linked account', function () {
    $this->user->forceFill(['student_id' => $this->student->id])->save();
    $this->withSession(['student_identity_verified' => $this->student->id, 'student_birth_verified' => ['student_id' => $this->student->id, 'verified_at' => now()->timestamp]]);
    $id = $this->postJson($this->base.'/auth/otp/request', ['email' => $this->student->email])->assertOk()->json('challenge_id');
    $code = Mail::queued(StudentOtpMail::class)->sole()->code;
    $this->postJson($this->base.'/auth/otp/verify', ['challenge_id' => $id, 'code' => $code])->assertOk()->assertJsonPath('authenticated', true)->assertJsonPath('linked', false);
    expect(session()->has('student_identity_verified'))->toBeFalse()->and(session()->has('student_birth_verified'))->toBeFalse();
    $this->getJson($this->base.'/me')->assertForbidden();
});
