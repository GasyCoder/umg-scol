<?php

use App\Actions\References\SaveAcademicYear;
use App\Actions\Users\AssignUserEstablishment;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

dataset('reference_models', [
    ['establishments', Establishment::class], ['academic-years', AcademicYear::class],
    ['levels', Level::class], ['programs', Program::class],
]);

it('allows superadmin to list create edit and change reference status', function (string $slug, string $model) {
    $this->actingAs($this->admin);
    $record = $model::factory()->create();
    $this->get('/references/'.$slug)->assertOk();
    $this->get('/references/'.$slug.'/create')->assertOk();
    $this->get('/references/'.$slug.'/'.$record->uuid.'/edit')->assertOk();
    $data = ['name' => 'Nouveau référentiel', 'is_active' => true];
    if ($slug === 'establishments' || $slug === 'levels') {
        $data['code'] = ' NEW-CODE ';
    }
    if ($slug === 'levels') {
        $data['sort_order'] = 7;
    }
    if ($slug === 'programs') {
        $data['establishment_id'] = Establishment::factory()->create()->id;
    }
    $this->post('/references/'.$slug, $data)->assertSessionHasNoErrors()->assertRedirect();
    expect($model::query()->where('name', 'Nouveau référentiel')->exists())->toBeTrue();
    $data['name'] = 'Référentiel modifié';
    if ($slug === 'establishments' || $slug === 'levels') {
        $data['code'] = $record->code;
    }
    $this->put('/references/'.$slug.'/'.$record->uuid, $data)->assertSessionHasNoErrors()->assertRedirect();
    $this->patch('/references/'.$slug.'/'.$record->uuid.'/status', ['is_active' => false])->assertRedirect();
    expect($record->fresh()->name)->toBe('Référentiel modifié')->and($record->fresh()->is_active)->toBeFalse();
    $this->patch('/references/'.$slug.'/'.$record->uuid.'/status', ['is_active' => true])->assertRedirect();
    expect($record->fresh()->is_active)->toBeTrue();
    $this->delete('/references/'.$slug.'/'.$record->uuid)->assertStatus(405);
    expect($record->fresh())->not->toBeNull();
})->with('reference_models');

it('returns 403 on direct reference urls for unprivileged establishment users', function (string $slug, string $model) {
    $user = User::factory()->create();
    $user->forceFill(['establishment_id' => Establishment::factory()->create()->id])->save();
    $user->assignRole('establishment');
    $record = $model::factory()->create();
    $this->actingAs($user);
    $this->get('/references/'.$slug)->assertForbidden();
    $this->get('/references/'.$slug.'/create')->assertForbidden();
    $this->get('/references/'.$slug.'/'.$record->uuid.'/edit')->assertForbidden();
    $this->post('/references/'.$slug, [])->assertForbidden();
    $this->put('/references/'.$slug.'/'.$record->uuid, [])->assertForbidden();
    $this->patch('/references/'.$slug.'/'.$record->uuid.'/status', ['is_active' => false])->assertForbidden();
})->with('reference_models');

it('normalizes and enforces unique establishment and level codes', function (string $slug, string $model) {
    $model::factory()->create(['code' => 'EXISTING']);
    $this->actingAs($this->admin)->post('/references/'.$slug, ['code' => ' existing ', 'name' => 'Doublon', 'is_active' => true, 'sort_order' => 0])
        ->assertSessionHasErrors('code');
    expect($model::count())->toBe(1);
})->with([['establishments', Establishment::class], ['levels', Level::class]]);

it('retains level order and deterministically sorts equal orders', function () {
    Level::factory()->create(['code' => 'Z', 'sort_order' => 9]);
    Level::factory()->create(['code' => 'B', 'sort_order' => 2]);
    Level::factory()->create(['code' => 'A', 'sort_order' => 2]);
    $this->actingAs($this->admin)->get('/references/levels')->assertInertia(fn (Assert $page) => $page
        ->where('records.data.0.code', 'A')->where('records.data.1.code', 'B')->where('records.data.2.sort_order', 9));
});

it('switches current years transactionally and keeps historical years', function () {
    $a = AcademicYear::factory()->create(['is_current' => true]);
    $b = AcademicYear::factory()->create();
    $this->actingAs($this->admin)->patch('/references/academic-years/'.$b->uuid.'/current')->assertRedirect();
    expect($a->fresh()->is_current)->toBeFalse()->and($b->fresh()->is_current)->toBeTrue()
        ->and(AcademicYear::where('is_current', true)->count())->toBe(1)->and(AcademicYear::count())->toBe(2);
    $this->patch('/references/academic-years/'.$b->uuid.'/status', ['is_active' => false])->assertRedirect();
    expect($b->fresh()->is_current)->toBeFalse();
    $this->patch('/references/academic-years/'.$b->uuid.'/current')->assertSessionHasErrors('is_current');
});

it('enforces the single-current invariant in the database itself', function () {
    AcademicYear::factory()->create(['is_current' => true]);
    expect(fn () => AcademicYear::factory()->create(['is_current' => true]))->toThrow(QueryException::class);
});

it('opens reception after selecting the current year through a POST method override', function () {
    $this->withoutVite();
    $year = AcademicYear::factory()->create(['is_current' => false, 'is_active' => true]);
    $this->actingAs($this->admin)->get('/scholarships/reception')
        ->assertInertia(fn (Assert $page) => $page->where('year', null));

    $this->withHeaders(['X-HTTP-Method-Override' => 'PATCH', 'X-Inertia' => 'true'])
        ->post('/references/academic-years/'.$year->uuid.'/current')
        ->assertStatus(303)->assertRedirect('/references/academic-years')->assertSessionHasNoErrors();

    expect($year->fresh()->is_current)->toBeTrue()->and($year->fresh()->is_active)->toBeTrue();
    expect(Activity::query()->where('subject_type', AcademicYear::class)->where('subject_id', $year->id)
        ->where('event', 'updated')->where('causer_id', $this->admin->id)->exists())->toBeTrue();

    $this->flushHeaders();
    $this->get('/scholarships/reception')->assertInertia(fn (Assert $page) => $page->where('year.id', $year->id));
});

it('keeps current-year permissions enforced with a POST method override', function () {
    $year = AcademicYear::factory()->create(['is_current' => false]);
    $user = User::factory()->create()->assignRole('central_scholarship_office');
    $this->actingAs($user)->withHeader('X-HTTP-Method-Override', 'PATCH')
        ->post('/references/academic-years/'.$year->uuid.'/current')->assertForbidden();
    expect($year->fresh()->is_current)->toBeFalse();
});

it('rolls back both the current-year switch and its audit on a failed save', function () {
    $a = AcademicYear::factory()->create(['is_current' => true]);
    $b = AcademicYear::factory()->create();
    $count = Activity::count();
    AcademicYear::saving(function (AcademicYear $year) use ($b): void {
        if ($year->id === $b->id && $year->is_current) {
            throw new RuntimeException('Simulated persistence failure');
        }
    });
    expect(fn () => app(SaveAcademicYear::class)->setCurrent($b))->toThrow(RuntimeException::class);
    expect($a->fresh()->is_current)->toBeTrue()->and($b->fresh()->is_current)->toBeFalse()->and(Activity::count())->toBe($count);
});

it('validates academic dates and switches current status during creation', function () {
    $this->actingAs($this->admin);
    $this->post('/references/academic-years', ['name' => '2026-2027', 'starts_at' => '2026-10-01', 'ends_at' => '2026-09-01', 'is_active' => true])->assertSessionHasErrors('ends_at');
    $a = AcademicYear::factory()->create(['is_current' => true]);
    $this->post('/references/academic-years', ['name' => '2026-2027', 'is_current' => true, 'is_active' => true])->assertSessionHasNoErrors()->assertRedirect();
    expect($a->fresh()->is_current)->toBeFalse()->and(AcademicYear::where('name', '2026-2027')->sole()->is_current)->toBeTrue();
});

it('filters programs by establishment with eager loaded relations', function () {
    $a = Establishment::factory()->create();
    $b = Establishment::factory()->create();
    Program::factory()->count(12)->create(['establishment_id' => $a->id]);
    Program::factory()->create(['establishment_id' => $b->id]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->actingAs($this->admin)->get('/references/programs?establishment_id='.$a->id)->assertInertia(fn (Assert $page) => $page
        ->has('records.data', 12)->where('records.data.0.establishment.id', $a->id));
    $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "establishments"'));
    expect($queries->count())->toBeLessThanOrEqual(3);
    DB::disableQueryLog();
});

it('enforces program foreign keys and code uniqueness per establishment', function () {
    $a = Establishment::factory()->create();
    $b = Establishment::factory()->create();
    Program::factory()->create(['establishment_id' => $a->id, 'code' => 'SHARED', 'name' => 'Même nom']);
    Program::factory()->create(['establishment_id' => $b->id, 'code' => 'SHARED', 'name' => 'Même nom']);
    $this->actingAs($this->admin)->post('/references/programs', ['establishment_id' => $a->id, 'code' => ' shared ', 'name' => 'Doublon', 'is_active' => true])->assertSessionHasErrors('code');
    expect(fn () => Program::factory()->create(['establishment_id' => 999999]))->toThrow(QueryException::class);
    expect(fn () => DB::table('establishments')->where('id', $a->id)->delete())->toThrow(QueryException::class);
    Program::factory()->count(2)->create(['establishment_id' => $a->id, 'code' => null]);
});

it('scopes future program management permission to the users establishment', function () {
    $a = Establishment::factory()->create();
    $b = Establishment::factory()->create();
    $user = User::factory()->create();
    $user->forceFill(['establishment_id' => $a->id])->save();
    $user->assignRole('establishment')->givePermissionTo('programs.manage');
    $own = Program::factory()->create(['establishment_id' => $a->id]);
    $other = Program::factory()->create(['establishment_id' => $b->id]);
    $this->actingAs($user)->get('/references/programs')->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.id', $own->id));
    $this->get('/references/programs/'.$other->uuid.'/edit')->assertForbidden();
    $this->patch('/references/programs/'.$other->uuid.'/status', ['is_active' => false])->assertForbidden();
    $this->put('/references/programs/'.$own->uuid, ['establishment_id' => $b->id, 'name' => 'Moved', 'is_active' => true])->assertForbidden();
    $this->post('/references/programs', ['establishment_id' => $b->id, 'name' => 'Foreign', 'is_active' => true])->assertForbidden();
});

it('attaches establishment users with correct eloquent relationships and auditing', function () {
    $establishment = Establishment::factory()->create();
    $user = User::factory()->create()->assignRole('establishment');
    $linked = app(AssignUserEstablishment::class)->handle($user, $establishment);
    expect($linked->establishment->is($establishment))->toBeTrue()->and($establishment->users()->sole()->is($user))->toBeTrue();
    expect(fn () => app(AssignUserEstablishment::class)->handle($linked, null))->toThrow(ValidationException::class);
    $this->actingAs($linked)->get('/dashboard')->assertOk();
    $establishment->update(['is_active' => false]);
    $this->get('/dashboard')->assertForbidden();
});

it('keeps other roles nullable and denies unusable establishment accounts', function (string $role) {
    $user = User::factory()->create()->assignRole($role);
    expect($user->establishment_id)->toBeNull();
    $response = $this->actingAs($user)->get('/dashboard');
    if (in_array($role, ['establishment', 'student'], true)) {
        $response->assertForbidden();
    } else {
        $response->assertOk();
    }
})->with(['superadmin', 'developer', 'central_scholarship_office', 'student', 'establishment']);

it('audits useful changes without institutional contact data and preserves actor', function (string $slug, string $model) {
    $this->actingAs($this->admin);
    $record = $model::factory()->create();
    $record->update(['name' => 'Audit modification']);
    $this->patch('/references/'.$slug.'/'.$record->uuid.'/status', ['is_active' => false])->assertRedirect();
    $this->patch('/references/'.$slug.'/'.$record->uuid.'/status', ['is_active' => true])->assertRedirect();
    $logs = Activity::where('log_name', 'references')->where('subject_type', $record->getMorphClass())->where('subject_id', $record->id)->get();
    expect($logs->count())->toBe(4)->and($logs->pluck('event')->all())->toBe(['created', 'updated', 'updated', 'updated']);
    foreach ($logs as $log) {
        expect($log->causer_id)->toBe($this->admin->id)->and($log->toJson())->not->toContain('"email"', '"phone"', '"address"');
    }
})->with('reference_models');

it('paginates and searches references on the server', function () {
    Establishment::factory()->count(20)->create();
    Establishment::factory()->create(['name' => 'Unique institution']);
    $this->actingAs($this->admin)->get('/references/establishments')->assertInertia(fn (Assert $page) => $page->has('records.data', 15)->where('records.total', 21));
    $this->get('/references/establishments?search=Unique institution')->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
});

it('audits contact-only changes without storing contact values', function () {
    $this->actingAs($this->admin);
    $record = Establishment::factory()->create();
    $record->update(['email' => 'private-contact@example.test', 'phone' => 'private-phone']);
    $log = Activity::where('log_name', 'references')->where('event', 'updated')->sole();
    expect($log->subject_id)->toBe($record->id)
        ->and($log->toJson())->not->toContain('private-contact@example.test', 'private-phone');
});

it('protects user foreign keys and does not allow profile reassignment', function () {
    $this->actingAs($this->admin)->patch(route('profile.update'), ['name' => $this->admin->name, 'email' => $this->admin->email, 'establishment_id' => Establishment::factory()->create()->id])->assertRedirect();
    expect($this->admin->fresh()->establishment_id)->toBeNull();
    expect(fn () => $this->admin->forceFill(['establishment_id' => 999999999])->save())->toThrow(QueryException::class);
});

it('validates the complete academic date interval on partial updates', function () {
    $year = AcademicYear::factory()->create(['starts_at' => '2026-10-01', 'ends_at' => '2027-06-30']);
    $this->actingAs($this->admin)->put('/references/academic-years/'.$year->uuid, ['name' => $year->name, 'ends_at' => '2026-09-01'])->assertSessionHasErrors('ends_at');
    expect($year->fresh()->ends_at->format('Y-m-d'))->toBe('2027-06-30');
});
