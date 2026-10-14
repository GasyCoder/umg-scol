<?php

use App\Models\AcademicYear;
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
 * Current year, establishment A, level L1:
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
    $declare($this->a, $this->l1, $this->gl, 'active', null, ['last_name' => 'RAKOTO', 'student_number' => 'GL-1']);
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
    $test->actingAs($user)->get('/students'.($query ? '?'.http_build_query($query) : ''))->assertOk()
        ->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->component('students/index')->toArray()['props'];
        });

    return $props;
}
function cardCounts(array $props): array
{
    return collect($props['cards'])->mapWithKeys(fn ($card) => [$card['label'] => [$card['active'], $card['inactive']]])->all();
}

it('starts global actors on establishment cards with current-year counts', function () {
    $props = browse($this, $this->central);
    expect($props['step'])->toBe('establishments')->and($props['year']['name'])->toBe('2026-2027')
        ->and(cardCounts($props))->toHaveCount(2)->toMatchArray(['École Normale' => [1, 0], 'Faculté des Sciences' => [6, 1]])
        ->and($props['records'])->toBeNull()->and(collect($props['trail'])->pluck('label')->all())->toBe(['Établissements']);
});
it('starts an establishment on the levels of its own establishment and never shows another one', function () {
    $props = browse($this, $this->staff);
    expect($props['step'])->toBe('levels')->and(cardCounts($props))->toBe(['Licence 1' => [5, 1], 'Licence 2' => [1, 0]])
        ->and(collect($props['trail'])->pluck('label')->all())->toBe(['Faculté des Sciences']);
    $this->actingAs($this->staff)->get('/students?establishment_id='.$this->b->id)->assertForbidden();
    expect(json_encode(browse($this, $this->staff, ['level_id' => $this->l1->id])))->not->toContain('École Normale');
});
it('groups programs by mention, with programs without mention and declarations without program under « Sans mention »', function () {
    $props = browse($this, $this->admin, ['establishment_id' => $this->a->id, 'level_id' => $this->l1->id]);
    expect($props['step'])->toBe('mentions')->and(cardCounts($props))->toBe(['Informatique' => [3, 1], 'Sans mention' => [2, 0]])
        ->and(collect($props['cards'])->pluck('id')->all())->toBe([$this->info->id, 'none']);
});
it('lists the programs of a mention, and of « Sans mention » including « Sans parcours »', function () {
    $base = ['establishment_id' => $this->a->id, 'level_id' => $this->l1->id];
    expect(cardCounts(browse($this, $this->admin, [...$base, 'mention_id' => $this->info->id])))->toBe(['Génie logiciel' => [2, 1], 'Réseaux' => [1, 0]])
        ->and(cardCounts(browse($this, $this->admin, [...$base, 'mention_id' => 'none'])))->toBe(['Mathématiques' => [1, 0], 'Sans parcours' => [1, 0]]);
});
it('ends on the student list of the selected program, with the full trail', function () {
    $props = browse($this, $this->admin, ['academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'level_id' => $this->l1->id, 'mention_id' => $this->info->id, 'program_id' => $this->gl->id]);
    expect($props['step'])->toBe('list')->and($props['records']['total'])->toBe(3)
        ->and(collect($props['trail'])->pluck('label')->all())->toBe(['Établissements', 'Faculté des Sciences', 'Licence 1', 'Informatique', 'Génie logiciel'])
        ->and($props['records']['data'][0]['program']['mention']['name'])->toBe('Informatique');
    $none = browse($this, $this->admin, ['academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'level_id' => $this->l1->id, 'mention_id' => 'none', 'program_id' => 'none']);
    expect($none['records']['total'])->toBe(1);
    $active = browse($this, $this->admin, ['academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'level_id' => $this->l1->id, 'program_id' => $this->gl->id, 'status' => 'active']);
    expect($active['records']['total'])->toBe(2);
});
it('shows a whole selection as a list on request', function () {
    $props = browse($this, $this->admin, ['academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'level_id' => $this->l1->id, 'mention_id' => $this->info->id, 'view' => 'list']);
    expect($props['step'])->toBe('list')->and($props['records']['total'])->toBe(4);
    expect(browse($this, $this->admin, ['academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'level_id' => $this->l1->id, 'mention_id' => 'none', 'view' => 'list'])['records']['total'])->toBe(2);
});
it('follows the chosen academic year and handles the absence of a current year', function () {
    expect(cardCounts(browse($this, $this->admin, ['academic_year_id' => $this->previous->id])))->toBe(['Faculté des Sciences' => [1, 0]]);
    AcademicYear::query()->update(['is_current' => false]);
    $props = browse($this, $this->admin);
    expect($props['year'])->toBeNull()->and($props['cards'])->toBe([]);
});
it('searches across years unless a year is requested', function () {
    expect(browse($this, $this->admin, ['search' => 'ANCIEN'])['records']['total'])->toBe(1)
        ->and(browse($this, $this->admin, ['search' => 'ANCIEN', 'academic_year_id' => $this->year->id])['records']['total'])->toBe(0)
        ->and(browse($this, $this->admin, ['search' => 'GL-1', 'academic_year_id' => $this->year->id])['records']['total'])->toBe(1);
});
it('validates drill-down parameters and refuses students', function () {
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
    $this->actingAs($this->admin)->put('/references/programs/'.$this->maths->id, ['establishment_id' => $this->a->id, 'mention_id' => $foreign->id, 'name' => 'Mathématiques'])->assertSessionHasErrors('mention_id');
    $this->actingAs($this->admin)->put('/references/programs/'.$this->maths->id, ['establishment_id' => $this->a->id, 'mention_id' => $this->info->id, 'name' => 'Mathématiques'])->assertRedirect();
    expect($this->maths->fresh()->mention_id)->toBe($this->info->id);
    expect(fn () => $this->reseaux->forceFill(['mention_id' => $foreign->id])->save())->toThrow(QueryException::class);
    $this->actingAs($this->admin)->put('/references/mentions/'.$this->info->id, ['establishment_id' => $this->b->id, 'name' => 'Informatique'])->assertSessionHasErrors('establishment_id');
});
