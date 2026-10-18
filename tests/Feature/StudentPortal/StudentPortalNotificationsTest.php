<?php

use App\Actions\Corrections\RequestStudentCorrection;
use App\Actions\Corrections\ReviewStudentCorrection;
use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Actions\Scholarships\TransitionScholarshipApplication;
use App\Actions\Students\NotifyStudentInformation;
use App\Enums\ScholarshipApplicationStatus;
use App\Enums\StudentCorrectionField;
use App\Enums\StudentCorrectionStatus;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    $this->bourse = 'http://'.config('portals.bourse_domain');
    $this->establishment = Establishment::factory()->create();
    $this->year = AcademicYear::factory()->create(['is_current' => true, 'is_active' => true]);
    $this->student = Student::factory()->create(['sex' => 'M', 'nationality' => 'Malagasy', 'mother_name' => 'RASOA Marie', 'phone' => '0341234567', 'address' => 'Lot II A 12', 'email' => 'officiel@example.test']);
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'status' => 'active']);
    $this->studentUser = User::factory()->create(['email' => 'login@example.test'])->assignRole('student');
    $this->studentUser->forceFill(['student_id' => $this->student->id])->save();
    $this->staff = User::factory()->create()->assignRole('establishment');
    $this->staff->forceFill(['establishment_id' => $this->establishment->id])->save();
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

function portalAs(TestCase $test): TestCase
{
    return $test->actingAs($test->studentUser, 'student')->withSession(['student_portal' => true, 'student_identity_verified' => $test->studentUser->student_id]);
}

it('saves a new sex, nationality or address at once, without waiting for validation', function (string $field, string $value, string $stored) {
    portalAs($this)->postJson($this->bourse.'/api/student/corrections', ['field' => $field, 'value' => $value])->assertCreated()
        ->assertJsonPath('correction.status', 'approved')->assertJsonPath('correction.direct', true)->assertJsonPath('correction.status_label', 'Enregistrée')
        ->assertJsonPath('message', StudentCorrectionField::from($field)->label().' mis à jour.');
    $value = $this->student->fresh()->getAttribute($field);
    expect($value instanceof BackedEnum ? $value->value : $value)->toBe($stored)
        ->and(StudentCorrectionRequest::sole())->status->toBe(StudentCorrectionStatus::Approved)->reviewed_by->toBeNull()
        ->and(Activity::query()->where('event', 'correction_applied')->sole()->causer_id)->toBe($this->studentUser->id);
})->with([['sex', 'F', 'F'], ['nationality', 'Comorien', 'Comorien'], ['address', 'Lot III B 4', 'Lot III B 4']]);

it('keeps identity, CIN, phone, email and BAC waiting for the staff decision', function (string $field, string $value) {
    portalAs($this)->postJson($this->bourse.'/api/student/corrections', ['field' => $field, 'value' => $value])->assertCreated()->assertJsonPath('correction.status', 'pending');
    expect(StudentCorrectionRequest::sole()->status)->toBe(StudentCorrectionStatus::Pending);
})->with([['mother_name', 'RAZAFY Lalao'], ['birth_date', '2001-02-04'], ['cin', '401011099999'], ['phone', '0381234567'], ['email', 'nouveau@example.test'], ['bac_series', 'C']]);

it('cancels a pending request overtaken by a direct change', function () {
    // A request made under the former rule, before the field became direct.
    $old = StudentCorrectionRequest::query()->create(['student_id' => $this->student->id, 'user_id' => $this->studentUser->id, 'field' => 'address', 'proposed_value' => 'Lot ancien', 'current_fingerprint' => str_repeat('0', 64), 'status' => 'pending']);
    portalAs($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'address', 'value' => 'Lot III B 4'])->assertCreated();
    expect($old->fresh()->status)->toBe(StudentCorrectionStatus::Cancelled);
    portalAs($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'address', 'value' => 'Lot III B 4'])->assertUnprocessable()->assertJsonValidationErrors('value');
});

it('derives the bell from decisions, applied changes and office emails, and counts the unread ones', function () {
    $rejected = app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::MotherName, 'RAZAFY Lalao', null);
    app(ReviewStudentCorrection::class)->handle($this->staff, $rejected, false, 'Justificatif illisible.');
    app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::Address, 'Lot III B 4', null);
    app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::Phone, '0381234567', null);
    app(NotifyStudentInformation::class)->handle($this->admin, $this->student, ['bac_series'], 'Apportez votre relevé du BAC.');
    // Another student's events never show.
    $other = Student::factory()->create(['email' => 'autre@example.test']);
    StudentRecord::factory()->create(['student_id' => $other->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id]);
    app(NotifyStudentInformation::class)->handle($this->admin, $other, ['phone']);

    // Sent and refused (mother), saved at once (address), sent (phone), office email.
    $feed = portalAs($this)->getJson($this->bourse.'/api/student/notifications')->assertOk()->assertJsonPath('unread', 5)->json('items');
    expect(collect($feed)->pluck('kind')->sort()->values()->all())->toBe(['applied', 'message', 'rejected', 'requested', 'requested'])
        ->and(collect($feed)->firstWhere('kind', 'rejected')['body'])->toBe('Nom et prénoms de la mère : Justificatif illisible.')
        ->and(collect($feed)->firstWhere('kind', 'message')['body'])->toBe('À compléter ou vérifier : Série BAC.')
        ->and(json_encode($feed))->not->toContain('0381234567')->not->toContain('RAZAFY');

    portalAs($this)->postJson($this->bourse.'/api/student/notifications/seen', [])->assertOk();
    portalAs($this)->getJson($this->bourse.'/api/student/notifications')->assertJsonPath('unread', 0);
    $this->travel(1)->minute();
    app(NotifyStudentInformation::class)->handle($this->admin, $this->student, ['cin']);
    portalAs($this)->getJson($this->bourse.'/api/student/notifications')->assertJsonPath('unread', 1);

    portalAs($this)->getJson($this->bourse.'/api/student/messages')->assertOk()->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.0.fields', ['Numéro CIN'])->assertJsonPath('messages.1.message', 'Apportez votre relevé du BAC.');
});

it('keeps the bell and the messages behind the verified identity, and serves the « Mes demandes » page', function () {
    $this->actingAs($this->studentUser, 'student')->withSession(['student_portal' => true])
        ->getJson($this->bourse.'/api/student/notifications')->assertForbidden();
    $this->get($this->bourse.'/demandes')->assertOk()->assertViewIs('bourse');
});

it('tells the student about their confirmation and the steps of their paper scholarship file', function () {
    $application = app(ReceiveScholarshipApplication::class)->handle(User::factory()->create()->assignRole('central_scholarship_office'), $this->student->id, 'DDB-77', null);
    app(TransitionScholarshipApplication::class)->handle($this->admin, $application, ScholarshipApplicationStatus::NeedsCorrection, 'Relevé de notes manquant.');
    $application->update(['internal_notes' => 'NOTE INTERNE']);
    $feed = collect(portalAs($this)->getJson($this->bourse.'/api/student/notifications')->assertOk()->json('items'));
    expect($feed->pluck('title')->all())->toContain('Dossier de bourse reçu', 'Correction du dossier demandée')
        ->and($feed->firstWhere('title', 'Correction du dossier demandée')['body'])->toBe('Relevé de notes manquant.')
        ->and($feed->toJson())->not->toContain('NOTE INTERNE');
});

it('tells the staff of a change saved without validation, without the new value', function () {
    portalAs($this)->postJson($this->bourse.'/api/student/corrections', ['field' => 'nationality', 'value' => 'Comorien'])->assertCreated();
    $notification = $this->staff->notifications()->sole();
    expect($notification->data['kind'])->toBe('information_updated')
        ->and($notification->data['message'])->toEndWith('a modifié : nationalité.')
        ->and(json_encode($notification->data))->not->toContain('Comorien');
});
