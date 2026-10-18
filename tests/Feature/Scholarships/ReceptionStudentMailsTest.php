<?php

use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Mail\ScholarshipFileReceivedMail;
use App\Mail\StudentRegisteredMail;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use App\Support\StudentMails;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* Adding a student missing from the reception search, and the emails sent to students. Fictitious people only. */
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    Mail::fake();
    $this->year = AcademicYear::factory()->create(['name' => '2026-2027', 'is_current' => true, 'is_active' => true]);
    $this->establishment = Establishment::factory()->create(['code' => 'FACMED', 'name' => 'Faculté de Médecine']);
    $this->level = Level::factory()->create(['code' => 'L1', 'name' => 'Licence 1']);
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->data = ['last_name' => 'REINIA', 'first_names' => 'Fara', 'sex' => 'F', 'birth_date' => '2004-02-03', 'nationality' => 'malagasy', 'email' => 'fara@example.test',
        'establishment_id' => $this->establishment->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'repeat_code' => 'N'];
});

it('offers to add a student missing from the reception search, prefilled from what was typed', function () {
    $this->actingAs($this->central)->get('/scholarships/reception?search=REINIA')
        ->assertInertia(fn (Assert $page) => $page->where('results', [])->where('canCreateStudent', true));

    $this->get('/students/create?from_reception=1&reception_search='.urlencode('REINIA Fara Soa'))
        ->assertInertia(fn (Assert $page) => $page->component('students/form')
            ->where('prefill', ['last_name' => 'REINIA', 'first_names' => 'Fara Soa'])
            ->where('reception.back_url', '/scholarships/reception?search=REINIA+Fara+Soa'));
    $this->get('/students/create?from_reception=1&reception_search=034+12+345+67')
        ->assertInertia(fn (Assert $page) => $page->where('prefill', ['phone' => '0341234567']));
    $this->get('/students/create?from_reception=1&reception_search=Fara@Example.test')
        ->assertInertia(fn (Assert $page) => $page->where('prefill', ['email' => 'fara@example.test']));
    // Without the reception context, the plain creation form.
    $this->get('/students/create')->assertInertia(fn (Assert $page) => $page->where('prefill', [])->where('reception', null));
});

it('comes back to the reception counter on the new student, ready to receive the paper file', function () {
    $this->actingAs($this->central)->post('/students', [...$this->data, 'from_reception' => true, 'reception_search' => 'REINIA'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/scholarships/reception/students/'.Student::query()->sole()->uuid.'?search=REINIA');
    $this->get('/scholarships/reception/students/'.Student::query()->sole()->uuid)
        ->assertInertia(fn (Assert $page) => $page->where('student.blockReason', null));
});

it('emails a new student, and the student whose paper file is received', function () {
    $this->actingAs($this->central)->post('/students', $this->data)->assertSessionHasNoErrors();
    Mail::assertQueued(StudentRegisteredMail::class, fn (StudentRegisteredMail $mail) => $mail->hasTo('fara@example.test')
        && $mail->studentName === 'REINIA Fara' && $mail->establishment === 'Faculté de Médecine' && $mail->academicYear === '2026-2027');

    $application = app(ReceiveScholarshipApplication::class)->handle($this->central, Student::query()->sole()->id, null, null);
    Mail::assertQueued(ScholarshipFileReceivedMail::class, function (ScholarshipFileReceivedMail $mail) use ($application) {
        $html = $mail->render();

        return $mail->hasTo('fara@example.test') && $mail->ddbNumber === 'DDB-2026-000001' && ! str_contains($html, $application->application_number)
            && str_contains($html, 'DDB-2026-000001') && str_contains($html, 'https://bourse.mahajanga-univ.mg') && ! str_contains($html, 'Faculté');
    });
});

it('sends nothing without an email on the file or when the email is switched off', function () {
    $this->actingAs($this->central)->post('/students', [...$this->data, 'email' => ''])->assertSessionHasNoErrors();
    app(ReceiveScholarshipApplication::class)->handle($this->central, Student::query()->sole()->id, null, null);
    Mail::assertNothingQueued();

    $this->actingAs($this->admin)->put('/settings/platform/student-mails', ['mail_student_registered' => false, 'mail_scholarship_received' => true])
        ->assertSessionHasNoErrors()->assertRedirect('/settings/platform');
    expect(StudentMails::settings())->toBe(['mail_student_registered' => false, 'mail_scholarship_received' => true])
        ->and(Activity::query()->where('event', 'student_mails_updated')->sole()->causer_id)->toBe($this->admin->id);
    $this->actingAs($this->central)->post('/students', [...$this->data, 'last_name' => 'AUTRE', 'email' => 'autre@example.test'])->assertSessionHasNoErrors();
    Mail::assertNotQueued(StudentRegisteredMail::class);
    $this->actingAs($this->admin)->get('/settings/platform')->assertInertia(fn (Assert $page) => $page->where('studentMails.values.mail_student_registered', false));
});

it('keeps the student emails setting to the platform administrator', function () {
    $this->actingAs($this->central)->put('/settings/platform/student-mails', ['mail_student_registered' => false, 'mail_scholarship_received' => false])->assertForbidden();
    $this->actingAs($this->admin)->put('/settings/platform/student-mails', ['mail_student_registered' => 'peut-être'])->assertSessionHasErrors(['mail_student_registered', 'mail_scholarship_received']);
    expect(StudentMails::settings())->toBe(['mail_student_registered' => true, 'mail_scholarship_received' => true]);
});
