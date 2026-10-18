<?php

use App\Models\AcademicYear;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ReportingFixture;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->f = ReportingFixture::build();
});

function dashboardData(TestCase $test, User $user, string $query = ''): array
{
    $data = null;
    $test->actingAs($user)->get('/dashboard'.$query)->assertOk()->assertInertia(function (Assert $page) use (&$data) {
        $page->component('dashboard');
        $data = $page->toArray()['props'];
    });

    return $data;
}
function kpi(array $props, string $key): ?int
{
    return collect($props['dashboard']['kpis'])->firstWhere('key', $key)['value'] ?? null;
}
function bars(?array $items): array
{
    return collect($items ?? [])->mapWithKeys(fn ($item) => [$item['label'] => $item['value']])->all();
}

it('limits an establishment to its own students, declarations, levels and programs', function () {
    $props = dashboardData($this, $this->f->staff_a);
    $d = $props['dashboard'];
    expect($d['scope'])->toMatchArray(['global' => false, 'establishment' => 'Faculté A'])->and($d['scope']['year']['name'])->toBe('2026-2027')
        ->and(kpi($props, 'active_students'))->toBe(3)->and(kpi($props, 'active_declarations'))->toBe(4)
        ->and(bars($d['charts']['students_by_level']))->toBe(['Licence 1' => 3, 'Licence 2' => 1])
        ->and(bars($d['charts']['students_by_program']))->toBe(['Informatique' => 2, 'Sans parcours' => 2])
        ->and($d['charts']['students_by_establishment'])->toBeNull()
        ->and($d['scholarships'])->toBeNull()->and($d['activity'])->toBeNull()->and($d['technical'])->toBeNull()
        ->and($props['years'])->toBe([]);
});
it('never counts or reveals establishment B on the dashboard of A', function () {
    $props = dashboardData($this, $this->f->staff_a);
    $json = json_encode($props['dashboard']);
    expect($json)->not->toContain('Institut B')
        ->and($props['dashboard']['corrections']['pending'])->toBe(1)
        ->and(kpi($props, 'links_pending'))->toBeNull()
        ->and($props['dashboard']['imports'])->toMatchArray(['imports' => 2, 'failed' => 1, 'with_anomalies' => 1, 'total_rows' => 15, 'rejected_rows' => 2, 'declared_rows' => 7])
        ->and(collect($props['dashboard']['imports']['recent'])->pluck('establishment')->unique()->all())->toBe(['Faculté A']);
    // B sees its own figures only.
    $b = dashboardData($this, $this->f->staff_b);
    expect(kpi($b, 'active_students'))->toBe(2)->and(json_encode($b['dashboard']))->not->toContain('Faculté A');
});
it('gives the central office global, read-only aggregates including paper files', function () {
    $props = dashboardData($this, $this->f->central_scholarship_office);
    $d = $props['dashboard'];
    expect($d['scope']['global'])->toBeTrue()
        ->and(kpi($props, 'active_students'))->toBe(5)->and(kpi($props, 'establishments'))->toBe(2)
        ->and(kpi($props, 'applications'))->toBe(3)->and(kpi($props, 'applications_today'))->toBe(1)->and(kpi($props, 'applications_pending'))->toBe(1)
        ->and($d['scholarships']['by_status'])->toBe(['pending' => 1, 'in_review' => 0, 'validated' => 1, 'needs_correction' => 0, 'rejected' => 1])
        ->and(bars($d['charts']['scholarships_by_establishment']))->toBe(['Faculté A' => 2, 'Institut B' => 1])
        ->and(bars($d['charts']['students_by_establishment']))->toBe(['Faculté A' => 4, 'Institut B' => 2])
        ->and($d['corrections']['pending'])->toBe(2)
        ->and($d['imports'])->toBeNull()->and($d['activity'])->toBeNull()
        ->and(count($d['kpis']))->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(8);
});
it('gives the superadmin the global institutional view with recent activity', function () {
    $props = dashboardData($this, $this->f->superadmin);
    $d = $props['dashboard'];
    expect(kpi($props, 'internal_users'))->toBe(5)->and(kpi($props, 'active_declarations'))->toBe(6)
        ->and($d['imports']['imports'])->toBe(3)->and($d['corrections'])->toBe(['pending' => 2, 'approved' => 1, 'rejected' => 0, 'cancelled' => 0])
        ->and($d['activity'])->toBeArray()->and($d['technical'])->toBeNull()
        ->and(collect($props['years'])->pluck('name')->all())->toContain('2026-2027', '2025-2026');
});
it('gives the developer the global view plus counters only for technical health', function () {
    $props = dashboardData($this, $this->f->developer);
    expect(kpi($props, 'active_students'))->toBe(5)->and($props['dashboard']['technical'])->toMatchArray(['failed_jobs' => 0, 'failed_imports' => 1])
        ->and(array_keys($props['dashboard']['technical']))->toBe(['failed_jobs', 'failed_imports', 'audit_entries_24h', 'last_audit_at']);
});
it('refuses student accounts on the internal dashboard', function () {
    $student = User::factory()->create()->assignRole('student');
    $this->actingAs($student)->get('/dashboard')->assertForbidden();
    $this->actingAs($student)->get('/reports')->assertForbidden();
    $this->actingAs($student)->get('/audits')->assertForbidden();
});
it('uses the current academic year by default and lets only global actors choose another', function () {
    $previous = dashboardData($this, $this->f->superadmin, '?year='.$this->f->previous->id)['dashboard'];
    expect($previous['scope']['year'])->toMatchArray(['name' => '2025-2026', 'is_current' => false])
        ->and($previous['kpis'][0]['value'])->toBe(1)->and($previous['confirmations'])->toBeNull();
    // An establishment cannot move to another year: the current one always applies.
    $own = dashboardData($this, $this->f->staff_a, '?year='.$this->f->previous->id)['dashboard'];
    expect($own['scope']['year']['name'])->toBe('2026-2027');
    $this->actingAs($this->f->superadmin)->get('/dashboard?year=999999')->assertSessionHasErrors('year');
});
it('shows a clear empty state when there is no current academic year', function () {
    AcademicYear::query()->update(['is_current' => false]);
    $props = dashboardData($this, $this->f->staff_a);
    expect($props['dashboard']['scope']['year'])->toBeNull()->and(kpi($props, 'active_students'))->toBe(0)
        ->and($props['dashboard']['charts']['students_by_level'])->toBe([])->and($props['dashboard']['confirmations'])->toBeNull();
    dashboardData($this, $this->f->central_scholarship_office);
});
it('counts student confirmations with the portal rule: current, stale and never confirmed', function () {
    expect(dashboardData($this, $this->f->staff_a)['dashboard']['confirmations'])->toBe(['confirmed' => 1, 'stale' => 1, 'never_confirmed' => 1, 'total' => 3])
        ->and(dashboardData($this, $this->f->superadmin)['dashboard']['confirmations'])->toBe(['confirmed' => 1, 'stale' => 1, 'never_confirmed' => 3, 'total' => 5]);
});

it('builds a zero-filled daily reception series within the selected year and permissions', function () {
    $today = now()->startOfDay();
    $files = ScholarshipApplication::query()->orderBy('id')->get();
    $files[0]->update(['received_at' => $today->copy()->addHours(10)]);
    $files[1]->update(['received_at' => $today->copy()->subDays(29)]);
    $files[2]->update(['received_at' => $today->copy()->subDays(30)]);
    $points = dashboardData($this, $this->f->superadmin)['dashboard']['charts']['scholarships_daily'];
    expect($points)->toHaveCount(30)
        ->and($points[0])->toBe(['date' => $today->copy()->subDays(29)->format('Y-m-d'), 'value' => 1])
        ->and($points[29])->toBe(['date' => $today->format('Y-m-d'), 'value' => 1])
        ->and($points[1]['value'])->toBe(0)
        ->and(array_sum(array_column($points, 'value')))->toBe(2);
    $previous = dashboardData($this, $this->f->superadmin, '?year='.$this->f->previous->id)['dashboard']['charts']['scholarships_daily'];
    expect(array_sum(array_column($previous, 'value')))->toBe(0)
        ->and(dashboardData($this, $this->f->staff_a)['dashboard']['charts']['scholarships_daily'])->toBeNull();
    AcademicYear::query()->update(['is_current' => false]);
    expect(dashboardData($this, $this->f->superadmin)['dashboard']['charts']['scholarships_daily'])->toBe([]);
});
it('lists items needing attention with non-zero counters only', function () {
    $watch = collect(dashboardData($this, $this->f->central_scholarship_office)['dashboard']['watch'])->pluck('value', 'label')->all();
    expect($watch)->toMatchArray(['Demandes de correction en attente' => 2, 'Dossiers reçus non encore vérifiés' => 1])
        ->and($watch)->not->toHaveKey('Dossiers en attente de correction')->and($watch)->not->toHaveKey('Demandes de rattachement en attente');
});
it('keeps the number of queries bounded whatever the volume (no N+1)', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        dashboardData($this, $this->f->superadmin);
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };
    $count(); // warm the permission cache
    $small = $count();
    foreach (range(1, 40) as $i) {
        $student = Student::factory()->create();
        StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->f->year->id, 'establishment_id' => $this->f->a->id, 'level_id' => $this->f->l1->id]);
    }
    // Same number of queries for 40 more students; at most the actor eager loads of the activity feed may differ.
    expect($count())->toBeLessThanOrEqual($small + 2)->and($small)->toBeLessThan(40);
});
it('aggregates several hundred declarations correctly in SQL', function () {
    $rows = [];
    $students = Student::factory()->count(300)->create();
    foreach ($students as $index => $student) {
        $rows[] = ['uuid' => (string) Str::uuid7(), 'student_id' => $student->id, 'academic_year_id' => $this->f->year->id, 'establishment_id' => $this->f->b->id, 'level_id' => $index % 3 === 0 ? $this->f->l2->id : $this->f->l1->id, 'status' => 'active', 'declared_by' => $this->f->superadmin->id, 'declared_at' => now(), 'created_at' => now(), 'updated_at' => now()];
    }
    StudentRecord::query()->insert($rows);
    $props = dashboardData($this, $this->f->staff_b);
    expect(kpi($props, 'active_students'))->toBe(302)->and(bars($props['dashboard']['charts']['students_by_level']))->toBe(['Licence 1' => 202, 'Licence 2' => 100])
        ->and($props['dashboard']['confirmations'])->toBe(['confirmed' => 0, 'stale' => 0, 'never_confirmed' => 302, 'total' => 302]);
});
