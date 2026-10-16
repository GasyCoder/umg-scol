<?php

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\ReportingFixture;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->f = ReportingFixture::build();
});

function reportProps(TestCase $test, User $user, string $query = ''): array
{
    $props = null;
    $test->actingAs($user)->get('/reports'.$query)->assertOk()->assertInertia(function (Assert $page) use (&$props) {
        $props = $page->component('reports/index')->toArray()['props'];
    });

    return $props;
}
function csvLines(string $csv): array
{
    return array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
}

it('limits an establishment report to its own establishment', function () {
    $props = reportProps($this, $this->f->staff_a);
    expect(collect($props['students'])->pluck('establishment')->unique()->all())->toBe(['Faculté A'])
        ->and(collect($props['imports'])->pluck('establishment')->all())->toBe(['Faculté A'])
        ->and($props['scholarships'])->toBeNull()->and(json_encode($props))->not->toContain('Institut B')
        ->and($props['exports'])->toBe(['students' => true, 'imports' => true, 'scholarships' => false]);
});
it('aggregates declarations by establishment, level and program with active and inactive counts', function () {
    $rows = collect(reportProps($this, $this->f->staff_a)['students'])->map(fn ($row) => [$row['level'], $row['program'], $row['active'], $row['inactive']])->all();
    expect($rows)->toBe([['Licence 1', 'Sans parcours', 1, 1], ['Licence 1', 'Informatique', 2, 0], ['Licence 2', 'Sans parcours', 1, 0]]);
});
it('applies the year filter, including for an establishment within its own perimeter', function () {
    $previous = reportProps($this, $this->f->staff_a, '?year='.$this->f->previous->id);
    expect($previous['scope']['year']['name'])->toBe('2025-2026')->and(collect($previous['students'])->sum('total'))->toBe(1)->and($previous['imports'])->toBe([]);
    expect(collect(reportProps($this, $this->f->staff_a)['students'])->sum('total'))->toBe(5);
});
it('aggregates imports, corrections and paper files exactly', function () {
    $props = reportProps($this, $this->f->superadmin);
    expect(collect($props['imports'])->keyBy('establishment')['Faculté A'])->toMatchArray(['imports' => 2, 'completed' => 1, 'failed' => 1, 'total_rows' => 15, 'valid_rows' => 7, 'rejected_rows' => 2, 'duplicate_rows' => 1, 'declared_rows' => 7])
        ->and($props['corrections'])->toBe(['pending' => 2, 'approved' => 1, 'rejected' => 0, 'cancelled' => 0])
        ->and(collect($props['scholarships'])->map(fn ($row) => [$row['establishment'], $row['level'], $row['pending'], $row['validated'], $row['rejected'], $row['total']])->all())
        ->toBe([['Faculté A', 'Licence 1', 1, 0, 1, 2], ['Institut B', 'Licence 1', 0, 1, 0, 1]]);
    expect(reportProps($this, $this->f->staff_a)['corrections'])->toBe(['pending' => 1, 'approved' => 1, 'rejected' => 0, 'cancelled' => 0]);
});
it('gives global actors global reports, within their module permissions', function () {
    foreach (['superadmin', 'developer'] as $role) {
        $props = reportProps($this, $this->f->{$role});
        expect(collect($props['students'])->pluck('establishment')->unique()->values()->all())->toBe(['Faculté A', 'Institut B'])->and($props['scholarships'])->not->toBeNull();
    }
    $central = reportProps($this, $this->f->central_scholarship_office);
    expect($central['imports'])->toBeNull()->and($central['students'])->not->toBeNull()->and($central['scholarships'])->not->toBeNull()
        ->and($central['exports'])->toBe(['students' => true, 'imports' => false, 'scholarships' => true]);
});
it('forbids students and enforces each export permission on the server', function () {
    $student = User::factory()->create()->assignRole('student');
    $this->actingAs($student)->get('/reports/students/export')->assertForbidden();
    $this->actingAs($this->f->staff_a)->get('/reports/scholarships/export')->assertForbidden();
    $this->actingAs($this->f->central_scholarship_office)->get('/reports/imports/export')->assertForbidden();
    $this->actingAs($this->f->superadmin)->get('/reports/unknown/export')->assertNotFound();
    Role::findByName('establishment')->revokePermissionTo('students.export');
    $this->actingAs($this->f->staff_a->fresh())->get('/reports/students/export')->assertForbidden();
});
it('exports the same aggregated figures as the page, inside the perimeter and without personal data', function () {
    $csv = $this->actingAs($this->f->staff_a)->get('/reports/students/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
    $lines = csvLines($csv);
    expect($lines[0])->toBe('Établissement;Niveau;Parcours;"Situations actives";"Situations inactives";Total')->and($lines)->toHaveCount(4)
        ->and($csv)->not->toContain('Institut B')->not->toContain('@example.test');
    $files = csvLines($this->actingAs($this->f->central_scholarship_office)->get('/reports/scholarships/export')->assertOk()->streamedContent());
    expect($files)->toHaveCount(3)->and($files[1])->toContain('Faculté A');
    $imports = csvLines($this->actingAs($this->f->superadmin)->get('/reports/imports/export?year='.$this->f->previous->id)->assertOk()->streamedContent());
    expect($imports)->toHaveCount(1);
    expect(Activity::query()->where('event', 'reports_exported')->pluck('properties')->map(fn ($p) => $p['report'])->all())->toBe(['students', 'scholarships', 'imports']);
});
