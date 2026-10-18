<?php

use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Enums\ScholarshipApplicationStatus;
use App\Mail\StudentInformationRequestMail;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentInformationRequest;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    $this->establishment = Establishment::factory()->create(['name' => 'Faculté des Sciences']);
    $this->year = AcademicYear::factory()->create(['name' => '2025-2026', 'is_current' => true, 'is_active' => true]);
    $this->level = Level::factory()->create(['name' => 'Licence 1']);
    // Uploaded by an establishment: no phone, no BAC, a CIN of 10 digits.
    $this->student = Student::factory()->create(['last_name' => 'RABE', 'first_names' => 'Hery', 'cin' => '5010123456', 'email' => 'hery@example.test', 'phone' => null, 'bac_series' => null, 'bac_year' => null, 'address' => 'Lot SECRET 7']);
    $this->record = StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id, 'status' => 'active']);
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
});

it('emails the student the fields to complete or check, and keeps only their names', function () {
    $this->actingAs($this->admin)->get('/students/'.$this->student->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('notification.has_email', true)->where('notification.last', null)
        ->where('notification.fields', fn ($fields) => collect($fields)->firstWhere('value', 'phone')['state'] === 'missing'
            && collect($fields)->firstWhere('value', 'cin')['state'] === 'doubtful'
            && collect($fields)->firstWhere('value', 'last_name')['state'] === null
            && collect($fields)->doesntContain('value', 'email')));

    $this->post('/students/'.$this->student->uuid.'/notifications', ['fields' => ['phone', 'bac_series', 'cin'], 'message' => 'Merci de passer à la scolarité.'])
        ->assertRedirect()->assertInertiaFlash('toast.message', 'Email envoyé à l’étudiant.');

    Mail::assertQueued(StudentInformationRequestMail::class, fn (StudentInformationRequestMail $mail) => $mail->hasTo('hery@example.test')
        && $mail->fields === [['label' => 'Numéro CIN', 'missing' => false], ['label' => 'Téléphone', 'missing' => true], ['label' => 'Série BAC', 'missing' => true]]);
    $html = (new StudentInformationRequestMail('RABE Hery', [['label' => 'Téléphone', 'missing' => true], ['label' => 'Numéro CIN', 'missing' => false]], 'Merci de passer à la scolarité.'))->render();
    expect($html)->toContain('Téléphone')->toContain('Non renseigné')->toContain('À vérifier')->toContain('Merci de passer à la scolarité.')->not->toContain('5010123456');

    $request = StudentInformationRequest::query()->sole();
    expect($request->fields)->toBe(['cin', 'phone', 'bac_series'])->and($request->sent_by)->toBe($this->admin->id);
    $audit = Activity::query()->where('event', 'student_information_requested')->sole();
    expect($audit->properties['fields'])->toBe(['cin', 'phone', 'bac_series'])
        ->and(json_encode($audit->properties))->not->toContain('5010123456')->not->toContain('hery@example.test');
    $this->get('/students/'.$this->student->uuid)->assertInertia(fn (Assert $page) => $page->where('notification.last.by', $this->admin->name)->where('notification.last.count', 3));
});

it('is reserved to the superadmin by default and opened to another role through the permission', function () {
    $this->actingAs($this->central)->get('/students/'.$this->student->uuid)->assertInertia(fn (Assert $page) => $page->where('notification', null));
    $this->post('/students/'.$this->student->uuid.'/notifications', ['fields' => ['phone']])->assertForbidden();
    $this->post('/students/notifications', ['students' => [$this->student->uuid]])->assertForbidden();
    Mail::assertNothingQueued();

    Role::findByName('central_scholarship_office')->givePermissionTo('students.notify');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->central->fresh())->post('/students/'.$this->student->uuid.'/notifications', ['fields' => ['phone']])->assertSessionHasNoErrors();
    Mail::assertQueued(StudentInformationRequestMail::class);
});

it('refuses a student without email, an inactive record or an empty sending', function () {
    $this->actingAs($this->admin);
    $this->post('/students/'.$this->student->uuid.'/notifications', ['fields' => [], 'message' => ''])->assertSessionHasErrors('fields');
    $this->post('/students/'.$this->student->uuid.'/notifications', ['fields' => ['unknown']])->assertSessionHasErrors('fields.0');
    $this->student->update(['email' => null]);
    $this->post('/students/'.$this->student->uuid.'/notifications', ['fields' => ['phone']])->assertSessionHasErrors('fields');
    $this->get('/students/'.$this->student->uuid)->assertInertia(fn (Assert $page) => $page->where('notification.has_email', false));
    Mail::assertNothingQueued();
    expect(StudentInformationRequest::count())->toBe(0);
});

it('emails a selection, each student with the fields of their own record, and counts the skipped ones', function () {
    $complete = Student::factory()->create(['email' => 'ok@example.test', 'cin' => '501012345679', 'phone' => '0341112233', 'first_names' => 'Aina', 'sex' => 'F', 'birth_date' => '2003-01-01', 'mother_name' => 'RASOA', 'cin_issued_at' => '2021-01-01', 'cin_issued_place' => 'Mahajanga', 'nationality' => 'Malagasy', 'address' => 'Lot 1', 'bac_series' => 'D', 'bac_year' => 2021]);
    $noEmail = Student::factory()->create(['email' => null, 'phone' => null]);
    $records = collect([$this->student, $complete, $noEmail])->map(fn (Student $student) => $student->is($this->student) ? $this->record
        : StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id]));

    $this->actingAs($this->admin)->post('/students/notifications', ['records' => $records->pluck('uuid')->all()])
        ->assertInertiaFlash('toast.message', '1 email(s) envoyé(s) · 1 sans information à signaler · 1 sans email.');
    Mail::assertQueuedCount(1);
    Mail::assertQueued(StudentInformationRequestMail::class, fn ($mail) => $mail->hasTo('hery@example.test') && in_array(['label' => 'Téléphone', 'missing' => true], $mail->fields, true));
    $this->post('/students/notifications', ['students' => [$this->student->uuid], 'applications' => [$this->student->uuid]])->assertSessionHasErrors();
});

it('offers to email the student when a scholarship file needs a correction, with the reason', function () {
    $application = app(ReceiveScholarshipApplication::class)->handle($this->central, $this->student->id, null, null);
    $this->actingAs($this->admin)->get('/scholarships/applications/'.$application->uuid)->assertInertia(fn (Assert $page) => $page->where('notification.has_email', true));
    $this->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page->where('canNotifyStudents', true));

    $this->post('/scholarships/applications/'.$application->uuid.'/status', ['status' => 'needs_correction', 'reason' => 'Relevé de notes manquant.', 'notify_student' => true])
        ->assertInertiaFlash('toast.message', 'Dossier '.$application->reference().' : '.ScholarshipApplicationStatus::NeedsCorrection->label().'. Étudiant prévenu par email.');
    Mail::assertQueued(StudentInformationRequestMail::class, fn ($mail) => $mail->note === 'Relevé de notes manquant.');
    expect(StudentInformationRequest::query()->sole()->scholarship_application_id)->toBe($application->id);

    // Without the right, the decision is still taken and the agent is told the student was not emailed.
    $student = Student::factory()->create(['email' => 'autre@example.test']);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id, 'status' => 'active']);
    $other = app(ReceiveScholarshipApplication::class)->handle($this->central, $student->id, null, null);
    $this->actingAs($this->central)->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page->where('canNotifyStudents', false));
    $this->post('/scholarships/applications/'.$other->uuid.'/status', ['status' => 'needs_correction', 'reason' => 'Relevé de notes manquant.', 'notify_student' => true])
        ->assertInertiaFlash('toast.type', 'warning');
    expect($other->fresh()->status)->toBe(ScholarshipApplicationStatus::NeedsCorrection)->and(StudentInformationRequest::count())->toBe(1);
});
