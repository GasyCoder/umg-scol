<?php

use App\Actions\Scholarships\SaveScholarshipCampaign;
use App\Models\AcademicYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create(['name' => 'Admin Audit'])->assignRole('superadmin');
    $this->developer = User::factory()->create(['name' => 'Dev Support'])->assignRole('developer');
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->staff = User::factory()->create()->assignRole('establishment');
    Activity::query()->delete();
});

/** An audit line written as the application does, optionally with values that must never be shown. */
function auditLine(User $actor, string $log, string $event, array $properties = [], ?string $at = null): Activity
{
    activity($log)->causedBy($actor)->performedOn($actor)->event($event)->withProperties($properties)->log($event);
    $activity = Activity::query()->latest('id')->first();
    if ($at !== null) {
        $activity->forceFill(['created_at' => $at])->save();
    }

    return $activity;
}
function auditRows(TestCase $test, User $user, string $query = ''): array
{
    $rows = null;
    $test->actingAs($user)->get('/audits'.$query)->assertOk()->assertInertia(function (Assert $page) use (&$rows) {
        $rows = $page->component('audits/index')->toArray()['props']['activities'];
    });

    return $rows;
}

it('requires audits.view: other internal roles and students get 403', function () {
    $line = auditLine($this->admin, 'scholarships', 'application_received');
    foreach ([$this->central, $this->staff, User::factory()->create()->assignRole('student')] as $user) {
        $this->actingAs($user)->get('/audits')->assertForbidden();
        $this->actingAs($user)->get('/audits/'.$line->id)->assertForbidden();
        $this->actingAs($user)->get('/audits/export')->assertForbidden();
    }
    auditRows($this, $this->admin);
    auditRows($this, $this->developer);
});
it('paginates the audit trail on the server, newest first', function () {
    foreach (range(1, 30) as $i) {
        auditLine($this->admin, 'scholarships', 'check_updated', ['application_id' => $i]);
    }
    $page1 = auditRows($this, $this->admin);
    expect($page1['total'])->toBe(30)->and($page1['data'])->toHaveCount(25)->and($page1['data'][0]['id'])->toBeGreaterThan($page1['data'][1]['id']);
    expect(auditRows($this, $this->admin, '?page=2')['data'])->toHaveCount(5);
});
it('filters by actor, module, event and period, keeping filters in the URL', function () {
    auditLine($this->admin, 'scholarships', 'application_received', [], '2026-10-01 10:00:00');
    auditLine($this->developer, 'scholarships', 'application_validated', [], '2026-10-05 10:00:00');
    auditLine($this->developer, 'student_imports', 'committed', [], '2026-10-10 10:00:00');
    expect(collect(auditRows($this, $this->admin, '?actor='.$this->developer->id)['data'])->pluck('event')->all())->toBe(['committed', 'application_validated'])
        ->and(collect(auditRows($this, $this->admin, '?module=scholarships')['data'])->pluck('event')->all())->toBe(['application_validated', 'application_received'])
        ->and(collect(auditRows($this, $this->admin, '?event=committed')['data'])->pluck('module')->all())->toBe(['student_imports'])
        ->and(collect(auditRows($this, $this->admin, '?from=2026-10-02&to=2026-10-05')['data'])->pluck('event')->all())->toBe(['application_validated']);
    $rows = auditRows($this, $this->admin, '?module=scholarships&event=application_received');
    expect(collect($rows['links'])->pluck('url')->filter()->implode(' '))->toContain('module=scholarships')->toContain('event=application_received');
    $this->actingAs($this->admin)->get('/audits?module=<script>')->assertSessionHasErrors('module');
    $this->actingAs($this->admin)->get('/audits?from=2026-10-05&to=2026-10-01')->assertSessionHasErrors('to');
});
it('shows an authorised detail with whitelisted metadata only', function () {
    $line = auditLine($this->admin, 'scholarships', 'application_validated', [
        'application_id' => 12, 'campaign_id' => 3, 'student_id' => 44, 'status' => 'validated', 'changed_fields' => ['phone', 'address'],
        'cin' => '401011099999', 'phone' => '0341112233', 'email' => 'perso@example.test', 'address' => 'Lot 12', 'birth_date' => '2002-01-01',
        'password' => 'secret-pass', 'code' => '123456', 'proposed_value' => 'Nouvelle valeur', 'access_token' => 'ya29.token', 'reason' => 'motif libre',
        'attributes' => ['status' => 'validated', 'email' => 'nouveau@example.test', 'last_name' => 'RAKOTO'], 'old' => ['status' => 'pending', 'email' => 'ancien@example.test'],
    ]);
    $response = $this->actingAs($this->admin)->get('/audits/'.$line->id)->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('audits/show')->where('activity.event_label', 'Dossier validé')
        ->where('activity.metadata', [['key' => 'student_id', 'value' => '44'], ['key' => 'campaign_id', 'value' => '3'], ['key' => 'application_id', 'value' => '12'], ['key' => 'status', 'value' => 'validated'], ['key' => 'changed_fields', 'value' => 'phone, address']]));
    $props = json_encode($response->viewData('page')['props']);
    foreach (['401011099999', '0341112233', 'perso@example.test', 'Lot 12', '2002-01-01', 'secret-pass', '123456', 'Nouvelle valeur', 'ya29.token', 'motif libre', 'nouveau@example.test', 'ancien@example.test', 'RAKOTO'] as $secret) {
        expect($props)->not->toContain($secret);
    }
    expect($props)->toContain('"field":"email"')->toContain('"hidden":true');
});
it('renders an empty administrative list explicitly', function () {
    $line = auditLine($this->admin, 'scholarships', 'applications_exported', ['filters' => []]);
    $this->actingAs($this->admin)->get('/audits/'.$line->id)->assertInertia(fn (Assert $page) => $page->where('activity.metadata', [['key' => 'filters', 'value' => 'aucun']]));
});
it('never hides the developer: actions appear and filter like any other actor', function () {
    $year = AcademicYear::factory()->create(['is_current' => true]);
    app(SaveScholarshipCampaign::class)->handle($this->developer, null, ['academic_year_id' => $year->id, 'name' => 'Campagne dev', 'starts_at' => null, 'ends_at' => null, 'notes' => null]);
    $rows = auditRows($this, $this->admin, '?actor='.$this->developer->id);
    expect($rows['data'])->not->toBeEmpty()->and($rows['data'][0])->toMatchArray(['actor' => 'Dev Support', 'role' => 'Développeur', 'event' => 'campaign_created']);
    $this->actingAs($this->admin)->get('/audits')->assertInertia(fn (Assert $page) => $page->where('options.actors', fn ($actors) => collect($actors)->pluck('name')->contains('Dev Support')));
    // The developer's own consultation of the audit is itself traced when it exports.
    $this->actingAs($this->developer)->get('/audits/export')->assertOk()->streamedContent();
    expect(Activity::query()->where('event', 'audits_exported')->where('causer_id', $this->developer->id)->exists())->toBeTrue();
});
it('keeps student accounts anonymous in the audit trail', function () {
    $student = User::factory()->create(['name' => 'etudiant.perso@example.test'])->assignRole('student');
    auditLine($student, 'student_portal', 'link_requested', ['student_id' => 7]);
    $rows = auditRows($this, $this->admin);
    expect($rows['data'][0]['actor'])->toBe('Compte étudiant n° '.$student->id)->and(json_encode($rows))->not->toContain('etudiant.perso@example.test');
});
it('requires audits.export to export', function () {
    Role::findByName('superadmin')->revokePermissionTo('audits.export');
    $this->actingAs($this->admin->fresh())->get('/audits/export')->assertForbidden();
    auditRows($this, $this->admin->fresh());
});
it('exports the filtered audit as CSV without personal data and with formula injection guarded', function () {
    $evil = User::factory()->create(['name' => '=HYPERLINK("http://evil")'])->assignRole('central_scholarship_office');
    auditLine($evil, 'scholarships', 'application_received', ['application_id' => 9, 'cin' => '401011099999', 'phone' => '0341112233', 'email' => 'perso@example.test']);
    auditLine($this->admin, 'student_imports', 'committed', ['total_rows' => 10]);
    $csv = $this->actingAs($this->admin)->get('/audits/export?module=scholarships')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
    expect($lines)->toHaveCount(2)->and($lines[0])->toStartWith('Date;Acteur;Rôle;Module')
        ->and($lines[1])->toContain('"\'=HYPERLINK')->not->toContain(';"=HYPERLINK')->toContain('application_id=9')->toContain('Dossier reçu')
        ->and($csv)->not->toContain('401011099999')->not->toContain('0341112233')->not->toContain('perso@example.test')->not->toContain('committed')->not->toContain('total_rows');
});
