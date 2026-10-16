<?php

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Current year (8 situations), establishment A, level L1:
 *   mention Info → programs Génie logiciel (2 active, 1 inactive) and Réseaux (1)
 *   program Maths without mention (1), no program (1)
 * Level L2 at A: 1 (Génie logiciel). Establishment B: 1. Previous year at A: 1.
 */
beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->previous = AcademicYear::factory()->create(['name' => '2025-2026']);
    $this->year = AcademicYear::factory()->create(['name' => '2026-2027', 'is_current' => true, 'is_active' => true]);
    $this->a = Establishment::factory()->create(['name' => 'Faculté des Sciences', 'code' => 'FS']);
    $this->b = Establishment::factory()->create(['name' => 'École Normale', 'code' => 'ENS']);
    $this->l1 = Level::factory()->create(['name' => 'Licence 1', 'code' => 'L1', 'sort_order' => 1]);
    $this->l2 = Level::factory()->create(['name' => 'Licence 2', 'code' => 'L2', 'sort_order' => 2]);
    $this->info = Mention::factory()->create(['establishment_id' => $this->a->id, 'name' => 'Informatique', 'code' => 'INFO']);
    $this->gl = Program::factory()->create(['establishment_id' => $this->a->id, 'mention_id' => $this->info->id, 'name' => 'Génie logiciel']);
    $this->reseaux = Program::factory()->create(['establishment_id' => $this->a->id, 'mention_id' => $this->info->id, 'name' => 'Réseaux']);
    $this->maths = Program::factory()->create(['establishment_id' => $this->a->id, 'name' => 'Mathématiques']);
    $declare = fn (Establishment $e, Level $l, ?Program $p, string $status = 'active', ?AcademicYear $y = null, array $identity = []) => StudentRecord::factory()->create([
        'student_id' => Student::factory()->create($identity)->id, 'academic_year_id' => ($y ?? $this->year)->id, 'establishment_id' => $e->id, 'level_id' => $l->id, 'program_id' => $p?->id, 'status' => $status,
    ]);
    $declare($this->a, $this->l1, $this->gl, 'active', null, ['last_name' => 'RAKOTO', 'cin' => '777000111222']);
    $declare($this->a, $this->l1, $this->gl);
    $declare($this->a, $this->l1, $this->gl, 'inactive');
    $declare($this->a, $this->l1, $this->reseaux);
    $declare($this->a, $this->l1, $this->maths);
    $declare($this->a, $this->l1, null);
    $declare($this->a, $this->l2, $this->gl);
    $declare($this->b, $this->l1, null);
    $declare($this->a, $this->l1, $this->gl, 'active', $this->previous, ['last_name' => 'ANCIEN']);
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->staff = User::factory()->create()->assignRole('establishment');
    $this->staff->forceFill(['establishment_id' => $this->a->id])->save();
});

function browse(TestCase $test, User $user, array $query = []): array
{
    $props = null;
    // The « Tous les étudiants » view; the cards are tested separately.
    $query = ['view' => 'list', ...$query];
    $test->actingAs($user)->get('/students?'.http_build_query($query))->assertOk()
        ->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->component('students/index')->toArray()['props'];
        });

    return $props;
}
function names(array $props): array
{
    return collect($props['records']['data'])->map(fn ($row) => $row['establishment']['code'].':'.($row['program']['name'] ?? '-').':'.$row['status'])->sort()->values()->all();
}

it('lists all the students of the current year together, each line with its establishment', function () {
    $props = browse($this, $this->central);
    expect($props['step'])->toBe('list')->and($props['cards'])->toBe([])
        ->and($props['year']['name'])->toBe('2026-2027')->and($props['records']['total'])->toBe(8)
        ->and(collect($props['records']['data'])->pluck('establishment.code')->unique()->sort()->values()->all())->toBe(['ENS', 'FS'])
        ->and($props['records']['data'][0])->toHaveKeys(['student', 'establishment', 'level', 'program', 'repeat_code', 'status'])
        ->and(collect($props['mentions'])->pluck('name')->all())->toBe(['Informatique']);
});
it('follows the chosen academic year, all years on request, and every year without a current one', function () {
    expect(browse($this, $this->admin, ['academic_year_id' => $this->previous->id])['records']['total'])->toBe(1);
    $all = browse($this, $this->admin, ['academic_year_id' => 'all']);
    expect($all['year'])->toBeNull()->and($all['records']['total'])->toBe(9);
    AcademicYear::query()->update(['is_current' => false]);
    $props = browse($this, $this->admin);
    expect($props['year'])->toBeNull()->and($props['records']['total'])->toBe(9);
    $this->actingAs($this->admin)->get('/students?academic_year_id=tous')->assertSessionHasErrors('academic_year_id');
});
it('filters by establishment and level', function () {
    expect(names(browse($this, $this->admin, ['establishment_id' => $this->b->id])))->toBe(['ENS:-:active'])
        ->and(browse($this, $this->admin, ['establishment_id' => $this->a->id])['records']['total'])->toBe(7)
        ->and(browse($this, $this->admin, ['establishment_id' => $this->a->id, 'level_id' => $this->l2->id])['records']['total'])->toBe(1)
        ->and(browse($this, $this->admin, ['level_id' => $this->l1->id])['records']['total'])->toBe(7);
});
it('limits an establishment account to its own establishment, in data and filter choices', function () {
    Mention::factory()->create(['establishment_id' => $this->b->id, 'name' => 'Lettres']);
    $props = browse($this, $this->staff);
    expect($props['records']['total'])->toBe(7)->and(collect($props['records']['data'])->pluck('establishment_id')->unique()->all())->toBe([$this->a->id])
        ->and(collect($props['options']['establishments'])->pluck('id')->all())->toBe([$this->a->id])
        ->and(collect($props['mentions'])->pluck('name')->all())->toBe(['Informatique'])
        ->and(json_encode(browse($this, $this->staff, ['search' => 'a', 'academic_year_id' => 'all'])))->not->toContain('École Normale');
    $this->actingAs($this->staff)->get('/students?establishment_id='.$this->b->id)->assertForbidden();
    expect(browse($this, $this->staff, ['establishment_id' => $this->a->id])['records']['total'])->toBe(7);
});
it('filters by mention and program, including « Sans mention » and « Sans parcours »', function () {
    $base = ['establishment_id' => $this->a->id, 'level_id' => $this->l1->id];
    expect(names(browse($this, $this->admin, [...$base, 'mention_id' => $this->info->id])))->toBe(['FS:Génie logiciel:active', 'FS:Génie logiciel:active', 'FS:Génie logiciel:inactive', 'FS:Réseaux:active'])
        ->and(names(browse($this, $this->admin, [...$base, 'mention_id' => 'none'])))->toBe(['FS:-:active', 'FS:Mathématiques:active'])
        ->and(names(browse($this, $this->admin, [...$base, 'mention_id' => 'none', 'program_id' => 'none'])))->toBe(['FS:-:active'])
        ->and(browse($this, $this->admin, ['program_id' => $this->gl->id])['records']['total'])->toBe(4)
        ->and(browse($this, $this->admin, ['program_id' => 'none'])['records']['total'])->toBe(2)
        ->and(browse($this, $this->admin, ['mention_id' => $this->info->id])['records']['data'][0]['program']['mention']['name'])->toBe('Informatique');
});
it('filters by status of the situation', function () {
    expect(browse($this, $this->admin, ['status' => 'active'])['records']['total'])->toBe(7)
        ->and(names(browse($this, $this->admin, ['status' => 'inactive'])))->toBe(['FS:Génie logiciel:inactive']);
    $this->actingAs($this->admin)->get('/students?status=registered')->assertSessionHasErrors('status');
});
it('searches within the selected year, or every year', function () {
    expect(browse($this, $this->admin, ['search' => 'ANCIEN'])['records']['total'])->toBe(0)
        ->and(browse($this, $this->admin, ['search' => 'ANCIEN', 'academic_year_id' => 'all'])['records']['total'])->toBe(1)
        ->and(browse($this, $this->admin, ['search' => '777000111222'])['records']['data'][0]['student']['last_name'])->toBe('RAKOTO');
});
it('sorts alphabetically and keeps the filters in the pagination links', function () {
    foreach (range(1, 30) as $i) {
        StudentRecord::factory()->create(['student_id' => Student::factory()->create(['last_name' => sprintf('ZZ%02d', $i)])->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->b->id, 'level_id' => $this->l1->id]);
    }
    $props = browse($this, $this->admin, ['establishment_id' => $this->b->id, 'status' => 'active']);
    expect($props['records']['total'])->toBe(31)->and($props['records']['data'])->toHaveCount(25)
        ->and($props['records']['data'][1]['student']['last_name'])->toBe('ZZ01')
        ->and($props['records']['next_page_url'])->toContain('establishment_id='.$this->b->id)->toContain('status=active');
    expect(browse($this, $this->admin, ['establishment_id' => $this->b->id, 'page' => 2])['records']['data'])->toHaveCount(6);
});
it('exports exactly the filtered selection, and requires one year', function () {
    expect(Activity::query()->where('event', 'students_exported')->count())->toBe(0);
    $this->actingAs($this->admin)->get('/students/export?'.http_build_query(['search' => 'RAKOTO']))->assertOk();
    $log = Activity::query()->where('event', 'students_exported')->sole();
    expect($log->properties['total_rows'])->toBe(1)->and($log->properties['academic_year_id'])->toBe($this->year->id);
    $this->actingAs($this->admin)->get('/students/export?'.http_build_query(['establishment_id' => $this->a->id, 'mention_id' => $this->info->id, 'program_id' => $this->gl->id]))->assertOk();
    // Active situations by default (2 of 3 in Génie logiciel L1 + 1 in L2), the inactive one on request.
    expect(Activity::query()->where('event', 'students_exported')->latest('id')->first()->properties['total_rows'])->toBe(3);
    $this->actingAs($this->admin)->get('/students/export?'.http_build_query(['program_id' => $this->gl->id, 'status' => 'inactive']))->assertOk();
    expect(Activity::query()->where('event', 'students_exported')->latest('id')->first()->properties['total_rows'])->toBe(1);
    $this->actingAs($this->admin)->get('/students/export?'.http_build_query(['academic_year_id' => $this->previous->id, 'search' => 'ANCIEN']))->assertOk();
    expect(Activity::query()->where('event', 'students_exported')->latest('id')->first()->properties['total_rows'])->toBe(1);
    $this->actingAs($this->admin)->get('/students/export?academic_year_id=all')->assertNotFound();
});
it('validates list parameters and refuses students', function () {
    $this->actingAs($this->admin)->get('/students?mention_id=abc')->assertSessionHasErrors('mention_id');
    $this->actingAs($this->admin)->get('/students?program_id=0')->assertSessionHasErrors('program_id');
    $this->actingAs(User::factory()->create()->assignRole('student'))->get('/students')->assertForbidden();
});

// Mention referential
it('lets authorised actors manage mentions within their perimeter', function () {
    $this->actingAs($this->admin)->post('/references/mentions', ['establishment_id' => $this->b->id, 'code' => 'lettres', 'name' => 'Lettres'])->assertRedirect('/references/mentions');
    expect(Mention::query()->where('code', 'LETTRES')->value('establishment_id'))->toBe($this->b->id);
    $this->actingAs($this->staff)->post('/references/mentions', ['establishment_id' => $this->b->id, 'name' => 'Intrusion'])->assertForbidden();
    $this->actingAs($this->central)->get('/references/mentions')->assertForbidden();
    $this->actingAs($this->admin)->get('/references/programs')->assertInertia(fn (Assert $page) => $page->where('records.data', fn ($rows) => collect($rows)->firstWhere('name', 'Génie logiciel')['mention']['name'] === 'Informatique'));
});
it('keeps a program and its mention in the same establishment', function () {
    $foreign = Mention::factory()->create(['establishment_id' => $this->b->id]);
    $this->actingAs($this->admin)->put('/references/programs/'.$this->maths->uuid, ['establishment_id' => $this->a->id, 'mention_id' => $foreign->id, 'name' => 'Mathématiques'])->assertSessionHasErrors('mention_id');
    $this->actingAs($this->admin)->put('/references/programs/'.$this->maths->uuid, ['establishment_id' => $this->a->id, 'mention_id' => $this->info->id, 'name' => 'Mathématiques'])->assertRedirect();
    expect($this->maths->fresh()->mention_id)->toBe($this->info->id);
    expect(fn () => $this->reseaux->forceFill(['mention_id' => $foreign->id])->save())->toThrow(QueryException::class);
    $this->actingAs($this->admin)->put('/references/mentions/'.$this->info->uuid, ['establishment_id' => $this->b->id, 'name' => 'Informatique'])->assertSessionHasErrors('establishment_id');
});

it('starts with the establishment cards and keeps the step-by-step navigation down to the list', function () {
    $this->actingAs($this->central)->get('/students')->assertOk()->assertInertia(fn (Assert $page) => $page->where('step', 'establishments')
        ->where('records', null)->where('cards', fn ($cards) => collect($cards)->pluck('code')->sort()->values()->all() === ['ENS', 'FS']));
    $this->get('/students?establishment_id='.$this->a->id)->assertInertia(fn (Assert $page) => $page->where('step', 'levels')
        ->where('trail', fn ($trail) => collect($trail)->pluck('label')->first() === 'Établissements' && count($trail) === 2));
    // A search or « all years » always shows the whole list.
    $this->get('/students?search=zzz')->assertInertia(fn (Assert $page) => $page->where('step', 'list'));
    $this->get('/students?academic_year_id=all')->assertInertia(fn (Assert $page) => $page->where('step', 'list'));
});

it('starts an establishment account at its own levels', function () {
    $this->actingAs($this->staff)->get('/students')->assertOk()->assertInertia(fn (Assert $page) => $page->where('step', 'levels')
        ->where('trail', fn ($trail) => count($trail) === 1));
});

it('counts the selection above the list, whatever the status filter', function () {
    $stats = browse($this, $this->admin, ['status' => 'active'])['stats'];
    expect($stats)->toMatchArray(['total' => 8, 'active' => 7, 'inactive' => 1, 'establishments' => 2]);
    expect(browse($this, $this->staff)['stats'])->toMatchArray(['total' => 7, 'establishments' => 1]);
    $this->actingAs($this->admin)->get('/students')->assertInertia(fn (Assert $page) => $page->where('step', 'establishments')->where('stats.total', 8));
});

it('deactivates and reactivates the ticked situations, each one checked on its own', function () {
    $records = StudentRecord::query()->where('academic_year_id', $this->year->id)->where('establishment_id', $this->a->id)->where('status', 'active')->take(2)->get();
    $foreign = StudentRecord::query()->where('establishment_id', $this->b->id)->sole();
    $uuids = [...$records->pluck('uuid')->all(), $foreign->uuid];

    $this->actingAs($this->staff)->post('/students/bulk', ['action' => 'deactivate', 'records' => $uuids])->assertRedirect()
        ->assertInertiaFlash('toast.message', '2 situation(s) désactivée(s) · 1 refusée(s) (droits ou contrôles).');
    expect($records->map(fn ($record) => $record->fresh()->status->value)->unique()->all())->toBe(['inactive'])
        ->and($foreign->fresh()->status->value)->toBe('active');

    $this->post('/students/bulk', ['action' => 'activate', 'records' => $records->pluck('uuid')->all()])->assertRedirect();
    expect($records->map(fn ($record) => $record->fresh()->status->value)->unique()->all())->toBe(['active']);
    $this->post('/students/bulk', ['action' => 'activate', 'records' => $records->pluck('uuid')->all()])
        ->assertInertiaFlash('toast.message', '0 situation(s) réactivée(s) · 2 déjà dans cet état.');
});

it('moves the ticked students to the trash only with the trash permission, and validates the request', function () {
    $record = StudentRecord::query()->where('establishment_id', $this->b->id)->sole();
    $this->actingAs($this->admin)->post('/students/bulk', ['action' => 'trash', 'records' => [$record->uuid]])->assertRedirect();
    expect(Student::query()->whereKey($record->student_id)->exists())->toBeFalse();

    $other = StudentRecord::query()->where('establishment_id', $this->a->id)->first();
    $this->actingAs($this->staff)->post('/students/bulk', ['action' => 'trash', 'records' => [$other->uuid]])
        ->assertInertiaFlash('toast.message', '0 étudiant(s) déplacé(s) dans la corbeille · 1 refusée(s) (droits ou contrôles).');
    expect(Student::query()->whereKey($other->student_id)->exists())->toBeTrue();

    $this->post('/students/bulk', ['action' => 'purge', 'records' => [$other->uuid]])->assertSessionHasErrors('action');
    $this->post('/students/bulk', ['action' => 'deactivate', 'records' => []])->assertSessionHasErrors('records');
});

it('exports exactly the ticked situations, inactive ones included', function () {
    $ticked = StudentRecord::query()->where('academic_year_id', $this->year->id)->where('status', 'inactive')->pluck('uuid')->all();
    $this->actingAs($this->admin)->postJson('/students/export', ['academic_year_id' => $this->year->id, 'records' => $ticked])->assertOk();
    expect(Activity::query()->where('event', 'students_exported')->latest('id')->first()->properties['total_rows'])->toBe(1);
});
