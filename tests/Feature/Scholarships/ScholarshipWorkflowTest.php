<?php

use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Actions\Scholarships\TransitionScholarshipApplication;
use App\Actions\Scholarships\TransitionScholarshipCampaign;
use App\Actions\Scholarships\UpdateScholarshipCheck;
use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCampaignStatus;
use App\Enums\ScholarshipCheckStatus;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Program;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationStatusHistory;
use App\Models\ScholarshipCampaign;
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
    $this->campaign = ScholarshipCampaign::factory()->create(['academic_year_id' => $this->year->id, 'name' => 'Bourse 2025-2026', 'status' => 'open', 'created_by' => $this->admin->id]);
});

function receiveFile(TestCase $test, ?Student $student = null, ?string $ddb = null, ?User $actor = null): ScholarshipApplication
{
    return app(ReceiveScholarshipApplication::class)->handle($actor ?? $test->central, $test->campaign->id, ($student ?? $test->student)->id, $ddb, null);
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

// Campaigns
it('lets campaign managers create a draft campaign and audits it', function () {
    $year = AcademicYear::factory()->create(['name' => '2026-2027', 'is_active' => true]);
    $this->actingAs($this->admin)->post('/scholarships/campaigns', ['academic_year_id' => $year->id, 'name' => 'Bourse nationale 2026-2027', 'starts_at' => '2026-11-01', 'ends_at' => '2026-12-15'])->assertRedirect('/scholarships/campaigns');
    $campaign = ScholarshipCampaign::where('academic_year_id', $year->id)->sole();
    expect($campaign->status)->toBe(ScholarshipCampaignStatus::Draft)->and($campaign->created_by)->toBe($this->admin->id);
    expect(Activity::where('log_name', 'scholarship_campaigns')->where('event', 'campaign_created')->where('subject_id', $campaign->id)->exists())->toBeTrue();
    $this->post('/scholarships/campaigns', ['academic_year_id' => $year->id, 'name' => 'Bourse nationale 2026-2027'])->assertSessionHasErrors('name');
    $this->post('/scholarships/campaigns', ['academic_year_id' => $year->id, 'name' => 'Autre', 'starts_at' => '2026-12-01', 'ends_at' => '2026-11-01'])->assertSessionHasErrors('ends_at');
});
it('follows the campaign lifecycle and refuses incoherent transitions', function () {
    $campaign = ScholarshipCampaign::factory()->create(['academic_year_id' => $this->year->id, 'status' => 'draft', 'created_by' => $this->admin->id]);
    $transition = fn (string $status) => app(TransitionScholarshipCampaign::class)->handle($this->admin, $campaign, ScholarshipCampaignStatus::from($status));
    expect(fn () => $transition('closed'))->toThrow(ValidationException::class);
    expect(fn () => $transition('archived'))->toThrow(ValidationException::class);
    $transition('scheduled');
    expect(fn () => $transition('draft'))->toThrow(ValidationException::class);
    foreach (['open', 'closed', 'archived'] as $status) {
        expect($transition($status)->status->value)->toBe($status);
    }
    expect(fn () => $transition('open'))->toThrow(ValidationException::class);
    expect(Activity::where('event', 'campaign_status_changed')->where('subject_id', $campaign->id)->count())->toBe(4);
    $this->actingAs($this->admin)->put('/scholarships/campaigns/'.$campaign->uuid, ['academic_year_id' => $this->year->id, 'name' => 'Renommée'])->assertSessionHasErrors('campaign');
});
it('refuses to open a campaign of an inactive academic year', function () {
    $year = AcademicYear::factory()->create(['is_active' => false]);
    $campaign = ScholarshipCampaign::factory()->create(['academic_year_id' => $year->id, 'status' => 'draft', 'created_by' => $this->admin->id]);
    expect(fn () => app(TransitionScholarshipCampaign::class)->handle($this->admin, $campaign, ScholarshipCampaignStatus::Open))->toThrow(ValidationException::class);
});
it('restricts campaign administration to campaigns.manage and keeps the central office read-only', function () {
    $this->actingAs($this->central)->get('/scholarships/campaigns')->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('campaigns.data.0.targets', []));
    $this->post('/scholarships/campaigns', ['academic_year_id' => $this->year->id, 'name' => 'Interdite'])->assertForbidden();
    $this->post('/scholarships/campaigns/'.$this->campaign->uuid.'/status', ['status' => 'closed'])->assertForbidden();
    $this->actingAs($this->staff)->get('/scholarships/campaigns')->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole('developer'))->post('/scholarships/campaigns/'.$this->campaign->uuid.'/status', ['status' => 'closed'])->assertRedirect();
    expect($this->campaign->fresh()->status)->toBe(ScholarshipCampaignStatus::Closed);
});
it('refuses reception outside an open campaign', function (string $status) {
    $this->campaign->update(['status' => $status]);
    expect(fn () => receiveFile($this))->toThrow(ValidationException::class);
    expect(ScholarshipApplication::count())->toBe(0);
})->with(['draft', 'scheduled', 'closed', 'archived']);

// Reception
it('lets the central office receive a paper file with server-derived context', function () {
    $this->actingAs($this->central)->post('/scholarships/applications', ['campaign_id' => $this->campaign->id, 'student_id' => $this->student->id, 'ddb_number' => '  ddb 0042 ', 'student_record_id' => 999, 'received_by' => $this->admin->id, 'status' => 'validated'])
        ->assertRedirect();
    $application = ScholarshipApplication::sole();
    expect($application->status)->toBe(ScholarshipApplicationStatus::Pending)->and($application->student_record_id)->toBe($this->record->id)
        ->and($application->received_by)->toBe($this->central->id)->and($application->received_at)->not->toBeNull()
        ->and($application->ddb_number)->toBe('ddb 0042')->and($application->application_number)->toBe('BRS'.$this->campaign->id.'-000001')
        ->and($application->checks()->orderBy('id')->pluck('key')->all())->toBe(array_keys(config('scholarships.checks')));
    expect($this->campaign->fresh()->last_application_sequence)->toBe(1);
});
it('denies reception without the receive permission', function () {
    $this->actingAs($this->staff)->post('/scholarships/applications', ['campaign_id' => $this->campaign->id, 'student_id' => $this->student->id])->assertForbidden();
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
    $this->actingAs($this->central)->post('/scholarships/applications', ['campaign_id' => $this->campaign->id, 'student_id' => $studentId])->assertSessionHasErrors('student_id');
    expect(ScholarshipApplication::count())->toBe(0)->and($this->campaign->fresh()->last_application_sequence)->toBe(0);
})->with(['unknown', 'inactive student', 'no declaration', 'other year only', 'inactive declaration', 'inactive level']);
it('selects the active declaration of the campaign year among several', function () {
    $other = Establishment::factory()->create();
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $other->id, 'status' => 'inactive']);
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => AcademicYear::factory()->create()->id, 'establishment_id' => $other->id, 'status' => 'active']);
    expect(receiveFile($this)->student_record_id)->toBe($this->record->id);
});
it('numbers files uniquely per campaign and enforces it in SQL', function () {
    $first = receiveFile($this);
    $second = receiveFile($this, declaredStudent($this));
    expect($second->application_number)->toBe('BRS'.$this->campaign->id.'-000002');
    expect(fn () => DB::table('scholarship_applications')->where('id', $second->id)->update(['application_number' => $first->application_number]))->toThrow(QueryException::class);
});
it('allows one file per student and campaign, in the action and in SQL', function () {
    $first = receiveFile($this);
    $this->actingAs($this->central)->post('/scholarships/applications', ['campaign_id' => $this->campaign->id, 'student_id' => $this->student->id])->assertSessionHasErrors('student_id');
    expect(fn () => DB::table('scholarship_applications')->insert([...$first->only(['scholarship_campaign_id', 'student_id', 'student_record_id', 'received_by']), 'uuid' => (string) Str::uuid7(), 'application_number' => 'X-1', 'sequence' => 99, 'status' => 'pending', 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
    $other = ScholarshipCampaign::factory()->create(['academic_year_id' => $this->year->id, 'status' => 'open', 'created_by' => $this->admin->id]);
    expect(app(ReceiveScholarshipApplication::class)->handle($this->central, $other->id, $this->student->id, null, null)->application_number)->toBe('BRS'.$other->id.'-000001');
});
it('rejects a DDB number already used in the same campaign only', function () {
    receiveFile($this, ddb: 'DDB-12 34');
    expect(fn () => receiveFile($this, declaredStudent($this), 'ddb-1234'))->toThrow(ValidationException::class);
    receiveFile($this, declaredStudent($this));
    receiveFile($this, declaredStudent($this));
    $other = ScholarshipCampaign::factory()->create(['academic_year_id' => $this->year->id, 'status' => 'open', 'created_by' => $this->admin->id]);
    expect(app(ReceiveScholarshipApplication::class)->handle($this->central, $other->id, $this->student->id, 'DDB-12 34', null)->ddb_number)->toBe('DDB-12 34');
    expect(ScholarshipApplication::whereNull('ddb_key')->count())->toBe(2);
});
it('keeps a double click from creating two files', function () {
    $payload = ['campaign_id' => $this->campaign->id, 'student_id' => $this->student->id, 'ddb_number' => 'D1'];
    $this->actingAs($this->central)->post('/scholarships/applications', $payload)->assertRedirect();
    $this->post('/scholarships/applications', $payload)->assertSessionHasErrors('student_id');
    expect(ScholarshipApplication::count())->toBe(1)->and($this->campaign->fresh()->last_application_sequence)->toBe(1);
});
it('rechecks the declaration at reception time', function () {
    $this->actingAs($this->central)->get('/scholarships/reception?campaign_id='.$this->campaign->id.'&search=RABE')->assertInertia(fn (Assert $page) => $page->has('results', 1));
    $this->record->update(['status' => 'inactive']);
    $this->post('/scholarships/applications', ['campaign_id' => $this->campaign->id, 'student_id' => $this->student->id])->assertSessionHasErrors('student_id');
    expect(ScholarshipApplication::count())->toBe(0);
});
it('searches students on the server with a minimal preview', function (string $term) {
    declaredStudent($this, ['last_name' => 'AUTRE']);
    $response = $this->actingAs($this->central)->get('/scholarships/reception?campaign_id='.$this->campaign->id.'&search='.urlencode($term))
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
    $this->actingAs($this->central)->get('/scholarships/reception?campaign_id='.$this->campaign->id.'&search=501012345678')
        ->assertInertia(fn (Assert $page) => $page->where('results.0.application.id', $application->id)->where('results.0.birth_date', '2003-04-05')->missing('results.0.cin'));
});

// Isolation
it('denies students and establishments on every scholarship route', function (string $role) {
    $user = User::factory()->create()->assignRole($role);
    if ($role === 'establishment') {
        $user->forceFill(['establishment_id' => $this->establishment->id])->save();
    }
    $application = receiveFile($this);
    $this->actingAs($user);
    foreach (['/scholarships/campaigns', '/scholarships/reception', '/scholarships/applications', '/scholarships/applications/'.$application->uuid, '/scholarships/applications/export'] as $url) {
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
it('refuses any decision once the campaign is archived', function () {
    $application = receiveFile($this);
    $this->campaign->update(['status' => 'archived']);
    expect(fn () => app(TransitionScholarshipApplication::class)->handle($this->central, $application, ScholarshipApplicationStatus::Rejected, 'Motif suffisant.'))->toThrow(ValidationException::class);
});
it('keeps processing files after the campaign closes', function () {
    $application = receiveFile($this);
    app(TransitionScholarshipCampaign::class)->handle($this->admin, $this->campaign, ScholarshipCampaignStatus::Closed);
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
it('serializes a reception with a campaign closing', function () {
    $staleCampaign = ScholarshipCampaign::find($this->campaign->id);
    app(TransitionScholarshipCampaign::class)->handle($this->admin, $this->campaign, ScholarshipCampaignStatus::Closed);
    expect(fn () => app(ReceiveScholarshipApplication::class)->handle($this->central, $staleCampaign->id, $this->student->id, null, null))->toThrow(ValidationException::class);
    expect(ScholarshipApplication::count())->toBe(0);
});

// Audit and history
it('audits receptions, reviews, checks and decisions without personal data', function () {
    $validated = receiveFile($this, ddb: 'DDB-9');
    completeChecks($validated, $this->central);
    app(TransitionScholarshipApplication::class)->handle($this->central, $validated, ScholarshipApplicationStatus::Validated);
    $rejected = receiveFile($this, declaredStudent($this, ['cin' => '509999999999', 'phone' => '0320000001', 'email' => 'autre@example.test', 'address' => 'Lot AUTRE 9']));
    app(TransitionScholarshipApplication::class)->handle($this->central, $rejected, ScholarshipApplicationStatus::NeedsCorrection, 'Le téléphone 0347654321 diffère.');
    app(TransitionScholarshipApplication::class)->handle($this->central, $rejected, ScholarshipApplicationStatus::Rejected, 'Refus confidentiel détaillé.');
    $this->actingAs($this->central)->get('/scholarships/applications/export')->assertOk()->streamedContent();

    $events = Activity::where('log_name', 'scholarships')->orderBy('id')->pluck('event')->all();
    expect($events)->toContain('application_received', 'review_started', 'check_updated', 'application_validated', 'application_needs_correction', 'application_rejected', 'applications_exported');
    $received = Activity::where('event', 'application_received')->where('subject_id', $validated->id)->sole();
    // MySQL JSON columns do not keep key order: compare as a map.
    expect($received->causer_id)->toBe($this->central->id)->and($received->properties->only(['application_id', 'campaign_id', 'student_id', 'status'])->all())
        ->toEqual(['application_id' => $validated->id, 'campaign_id' => $this->campaign->id, 'student_id' => $this->student->id, 'status' => 'pending']);
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
        ->and($count('?search=zaf'))->toBe(1)->and($count('?campaign_id='.$this->campaign->id.'&level_id='.$this->level->id))->toBe(2)
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
    $empty = ['pending' => 0, 'in_review' => 0, 'needs_correction' => 0, 'validated' => 0, 'rejected' => 0];
    $this->get('/scholarships/applications?status=rejected')->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)
        ->where('statusCounts', [...$empty, 'pending' => 1, 'in_review' => 1, 'rejected' => 1]));
    $this->get('/scholarships/applications?status=pending&establishment_id='.$this->establishment->id)->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)
        ->where('statusCounts', [...$empty, 'pending' => 1, 'in_review' => 1]));
    $this->get('/scholarships/applications?search=zaf')->assertInertia(fn (Assert $page) => $page->where('statusCounts', [...$empty, 'rejected' => 1]));
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
it('exports filtered files as CSV without sensitive data and requires the export permission', function () {
    receiveFile($this, ddb: '=HYPERLINK("x")');
    $rejected = receiveFile($this, declaredStudent($this, ['last_name' => 'REJETE']));
    app(TransitionScholarshipApplication::class)->handle($this->central, $rejected, ScholarshipApplicationStatus::Rejected, 'Motif interne détaillé.');
    $csv = $this->actingAs($this->central)->get('/scholarships/applications/export?status=pending')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
    expect($csv)->toContain('"N° dossier";"N° DDB";"Date de naissance";Nom')->toContain('2003-04-05;RABE;Hery')->toContain('Faculté des Sciences')->toContain('Biologie')->toContain("'=HYPERLINK")
        ->not->toContain('REJETE')->not->toContain('501012345678')->not->toContain('0347654321')->not->toContain('hery@example.test')->not->toContain('Lot SECRET 7')->not->toContain('Motif interne');
    expect(substr_count(trim($csv), "\n"))->toBe(1);
    Role::findByName('central_scholarship_office')->revokePermissionTo('scholarships.export');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->get('/scholarships/applications/export')->assertForbidden();
    $this->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page->where('canExport', false));
});
it('shows the scholarship menu only to authorized accounts', function () {
    $this->actingAs($this->central)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.scholarships', ['campaigns' => true, 'receive' => true, 'applications' => true]));
    $this->actingAs($this->staff)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.scholarships', ['campaigns' => false, 'receive' => false, 'applications' => false]));
});
it('renders the file detail with official data, checklist and allowed actions', function () {
    $application = receiveFile($this, ddb: 'DDB-1');
    $this->actingAs($this->central)->get('/scholarships/applications/'.$application->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page->component('scholarships/applications/show')
        ->where('application.student.establishment', 'Faculté des Sciences')->where('application.student.program', 'Biologie')->has('application.checks', 5)
        ->where('actions', ['in_review', 'validated', 'needs_correction', 'rejected'])->where('canEditChecks', true)->where('canViewStudent', true)
        ->where('application.student.birth_date', '2003-04-05')->where('application.student.sex', 'M')->where('application.student.nationality', 'Malagasy')
        ->missing('application.student.cin')->missing('application.student.address')->missing('application.student.phone'));
});
it('identifies the student of a listed file by names and birth date without the CIN', function () {
    receiveFile($this);
    $this->actingAs($this->central)->get('/scholarships/applications')->assertInertia(fn (Assert $page) => $page
        ->where('applications.data.0.student', ['last_name' => 'RABE', 'first_names' => 'Hery', 'birth_date' => '2003-04-05']));
});
