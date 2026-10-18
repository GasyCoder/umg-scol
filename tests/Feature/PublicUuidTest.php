<?php

use App\Actions\Corrections\RequestStudentCorrection;
use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Enums\StudentCorrectionField;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentLinkRequest;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->establishment = Establishment::factory()->create();
    $this->year = AcademicYear::factory()->create(['is_current' => true, 'is_active' => true]);
    $this->level = Level::factory()->create();
    $this->mention = Mention::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->program = Program::factory()->create(['establishment_id' => $this->establishment->id, 'mention_id' => $this->mention->id]);
    $this->student = Student::factory()->create(['phone' => '0341234567']);
    $this->record = StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id, 'status' => 'active']);
    $this->studentUser = User::factory()->create()->assignRole('student');
    $this->studentUser->forceFill(['student_id' => $this->student->id])->save();
    $this->import = StudentImport::create(['establishment_id' => $this->establishment->id, 'academic_year_id' => $this->year->id, 'user_id' => $this->admin->id, 'original_filename' => 'liste.csv', 'stored_path' => 'imports/liste.csv', 'disk' => 'local']);
    $this->correction = app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::MotherName, 'RAZAFY Lalao', null);
    $this->link = StudentLinkRequest::create(['user_id' => User::factory()->create()->assignRole('student')->id, 'student_id' => $this->student->id, 'status' => 'pending', 'requested_at' => now()]);
    $this->application = app(ReceiveScholarshipApplication::class)->handle($this->central, $this->student->id, null, null);
    $this->activity = Activity::query()->latest('id')->firstOrFail();
});

it('gives every routed record a uuid v7 on creation while the integer id stays the primary key', function () {
    $models = [$this->establishment, $this->year, $this->level, $this->mention, $this->program, $this->record, $this->import, $this->correction, $this->link, $this->application, $this->activity];
    foreach ($models as $model) {
        /** @var Model $model */
        expect($model->fresh()->uuid)->toMatch(UUID_V7)->and($model->getKeyName())->toBe('id')->and($model->getKey())->toBeInt()
            ->and($model->getRouteKey())->toBe($model->uuid);
    }
    expect(collect($models)->pluck('uuid')->unique())->toHaveCount(count($models))
        ->and(config('activitylog.activity_model'))->toBe(Activity::class);
});

it('answers 404 to the sequential id in every URL family', function () {
    $this->actingAs($this->admin);
    $this->get('/references/establishments/'.$this->establishment->id.'/edit')->assertNotFound();
    $this->get('/references/establishments/'.$this->establishment->uuid.'/edit')->assertOk();
    $this->patch('/references/academic-years/'.$this->year->id.'/current')->assertNotFound();
    $this->get('/student-records/'.$this->record->id.'/edit')->assertNotFound();
    $this->get('/student-records/'.$this->record->uuid.'/edit')->assertOk();
    $this->get('/students/'.$this->student->uuid)->assertInertia(fn (Assert $page) => $page->where('records.0.uuid', $this->record->uuid));
    $this->get('/imports/'.$this->import->id)->assertNotFound();
    $this->get('/imports/'.$this->import->uuid)->assertOk();
    $this->get('/corrections/'.$this->correction->id)->assertNotFound();
    $this->get('/corrections/'.$this->correction->uuid)->assertOk();
    $this->get('/scholarships/applications/'.$this->application->id)->assertNotFound();
    $this->get('/scholarships/applications/'.$this->application->uuid)->assertOk();
    $this->get('/audits/'.$this->activity->id)->assertNotFound();
    $this->get('/audits/not-a-uuid')->assertNotFound();
    $this->get('/audits/'.$this->activity->uuid)->assertOk();
    expect($this->link->fresh()->status->value)->toBe('pending');

    $bourse = 'http://'.config('portals.bourse_domain').'/api/student/corrections/';
    $this->actingAs($this->studentUser, 'student')->withSession(['student_portal' => true, 'student_identity_verified' => $this->studentUser->student_id]);
    $this->getJson('http://'.config('portals.bourse_domain').'/api/student/corrections')->assertOk()
        ->assertJsonPath('corrections.0.uuid', $this->correction->uuid)->assertJsonMissingPath('corrections.0.id');
    $this->postJson($bourse.$this->correction->id.'/cancel')->assertNotFound();
    expect($this->correction->fresh()->status->value)->toBe('pending');
    $this->postJson($bourse.$this->correction->uuid.'/cancel')->assertOk()->assertJsonPath('correction.status', 'cancelled');
});

it('redirects to uuid URLs after server-side actions', function () {
    expect(route('imports.show', $this->import))->toEndWith('/imports/'.$this->import->uuid)
        ->and(route('corrections.show', $this->correction))->toEndWith('/corrections/'.$this->correction->uuid)
        ->and(route('scholarships.applications.show', $this->application))->toEndWith('/scholarships/applications/'.$this->application->uuid)
        ->and(route('references.programs.edit', $this->program))->toEndWith('/references/programs/'.$this->program->uuid.'/edit');
    $this->actingAs($this->admin)->post('/corrections/'.$this->correction->uuid.'/review', ['decision' => 'reject', 'rejection_reason' => 'Justificatif manquant.'])
        ->assertRedirect('/corrections/'.$this->correction->uuid);
    $this->post('/scholarships/applications/'.$this->application->uuid.'/checks', ['key' => 'identity', 'status' => 'ok'])
        ->assertRedirect('/scholarships/applications/'.$this->application->uuid);
    $this->patch('/student-records/'.$this->record->uuid.'/status', ['status' => 'inactive'])->assertRedirect('/students/'.$this->student->uuid);

    $other = Student::factory()->create();
    StudentRecord::factory()->create(['student_id' => $other->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id, 'status' => 'active']);
    $this->actingAs($this->central)->post('/scholarships/applications', ['student_id' => $other->id])
        ->assertRedirect('/scholarships/reception');
    expect($other->scholarshipApplications()->sole()->uuid)->toBeString();
});
