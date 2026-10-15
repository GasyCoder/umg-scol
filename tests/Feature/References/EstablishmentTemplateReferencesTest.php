<?php

use App\Enums\EstablishmentSector;
use App\Enums\TrainingType;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Domain;
use App\Models\Establishment;
use App\Models\Mention;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->establishment = Establishment::factory()->create();
});

// Domains (national referential, like levels)
it('lets reference managers list, create, edit and deactivate domains with an audit', function () {
    $this->actingAs($this->admin);
    $this->get('/references/domains/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('references/domains/form')->where('record', null));
    $this->post('/references/domains', ['code' => ' alsh ', 'name' => ' Arts, Lettres et Sciences Humaines ', 'sort_order' => 1, 'is_active' => true])
        ->assertSessionHasNoErrors()->assertRedirect('/references/domains');
    $domain = Domain::sole();
    expect($domain->code)->toBe('ALSH')->and($domain->name)->toBe('Arts, Lettres et Sciences Humaines')->and($domain->uuid)->not->toBeEmpty();
    $this->get('/references/domains?search=lettres')->assertInertia(fn (Assert $page) => $page->component('references/domains/index')->has('records.data', 1)->where('records.data.0.code', 'ALSH')->where('canCreate', true));
    $this->get('/references/domains?search=inconnu')->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
    $this->get('/references/domains/'.$domain->uuid.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->where('record.code', 'ALSH'));
    $this->put('/references/domains/'.$domain->uuid, ['code' => 'ALSH', 'name' => 'Arts et Lettres', 'sort_order' => 2])->assertSessionHasNoErrors()->assertRedirect();
    $this->patch('/references/domains/'.$domain->uuid.'/status', ['is_active' => false])->assertRedirect();
    expect($domain->fresh()->name)->toBe('Arts et Lettres')->and($domain->fresh()->sort_order)->toBe(2)->and($domain->fresh()->is_active)->toBeFalse();
    $this->delete('/references/domains/'.$domain->uuid)->assertStatus(405);
    expect(Activity::query()->where('subject_type', $domain->getMorphClass())->where('subject_id', $domain->id)->count())->toBeGreaterThanOrEqual(2);
});

it('validates domain codes, names and order', function (array $payload, string $field) {
    Domain::factory()->create(['code' => 'STS']);
    $this->actingAs($this->admin)->post('/references/domains', [...['code' => 'NEW', 'name' => 'Domaine', 'sort_order' => 0], ...$payload])->assertSessionHasErrors($field);
    expect(Domain::count())->toBe(1);
})->with([
    'duplicate code' => [['code' => ' sts '], 'code'],
    'invalid code' => [['code' => 'A B'], 'code'],
    'missing code' => [['code' => ''], 'code'],
    'missing name' => [['name' => ' '], 'name'],
    'negative order' => [['sort_order' => -1], 'sort_order'],
]);

it('forbids domain management to establishment and central accounts', function (string $role) {
    $user = User::factory()->create()->assignRole($role);
    if ($role === 'establishment') {
        $user->forceFill(['establishment_id' => $this->establishment->id])->save();
    }
    $domain = Domain::factory()->create();
    $this->actingAs($user);
    $this->get('/references/domains')->assertForbidden();
    $this->get('/references/domains/create')->assertForbidden();
    $this->post('/references/domains', ['code' => 'NEW', 'name' => 'Domaine', 'sort_order' => 0])->assertForbidden();
    $this->put('/references/domains/'.$domain->uuid, ['code' => 'NEW', 'name' => 'Domaine', 'sort_order' => 0])->assertForbidden();
    $this->patch('/references/domains/'.$domain->uuid.'/status', ['is_active' => false])->assertForbidden();
    expect(Domain::count())->toBe(1)->and($domain->fresh()->is_active)->toBeTrue();
})->with(['establishment', 'central_scholarship_office']);

// Mention domain
it('attaches a mention to an existing domain and offers domains on the mention form', function () {
    $domain = Domain::factory()->create(['code' => 'ALSH']);
    $this->actingAs($this->admin)->get('/references/mentions/create')->assertInertia(fn (Assert $page) => $page->has('domains', 1)->where('domains.0.code', 'ALSH'));
    $this->post('/references/mentions', ['establishment_id' => $this->establishment->id, 'code' => 'LET', 'name' => 'Lettres', 'domain_id' => $domain->id])->assertSessionHasNoErrors()->assertRedirect();
    $mention = Mention::sole();
    expect($mention->domain_id)->toBe($domain->id)->and($mention->domain->is($domain))->toBeTrue();
    $this->get('/references/mentions/'.$mention->uuid.'/edit')->assertInertia(fn (Assert $page) => $page->where('record.domain_id', $domain->id)->has('domains', 1));
    $this->put('/references/mentions/'.$mention->uuid, ['establishment_id' => $this->establishment->id, 'code' => 'LET', 'name' => 'Lettres', 'domain_id' => null])->assertSessionHasNoErrors();
    expect($mention->fresh()->domain_id)->toBeNull();
    $this->put('/references/mentions/'.$mention->uuid, ['establishment_id' => $this->establishment->id, 'code' => 'LET', 'name' => 'Lettres', 'domain_id' => 999999])->assertSessionHasErrors('domain_id');
});

// Program training type
it('records the training type of a program from the closed list', function () {
    $this->actingAs($this->admin)->post('/references/programs', ['establishment_id' => $this->establishment->id, 'name' => 'Tronc commun', 'training_type' => 'professional'])->assertSessionHasNoErrors()->assertRedirect();
    $program = Program::sole();
    expect($program->training_type)->toBe(TrainingType::Professional);
    $this->put('/references/programs/'.$program->uuid, ['establishment_id' => $this->establishment->id, 'name' => 'Tronc commun', 'training_type' => 'distance'])->assertSessionHasErrors('training_type');
    $this->put('/references/programs/'.$program->uuid, ['establishment_id' => $this->establishment->id, 'name' => 'Tronc commun', 'training_type' => null])->assertSessionHasNoErrors();
    expect($program->fresh()->training_type)->toBeNull();
});

// Establishment sector
it('records the public or private sector of an establishment', function () {
    $this->actingAs($this->admin)->post('/references/establishments', ['code' => 'ESP', 'name' => 'École privée', 'sector' => 'private', 'is_active' => true])->assertSessionHasNoErrors()->assertRedirect();
    $establishment = Establishment::query()->where('code', 'ESP')->sole();
    expect($establishment->sector)->toBe(EstablishmentSector::Private);
    $this->put('/references/establishments/'.$establishment->uuid, ['code' => 'ESP', 'name' => 'École privée', 'sector' => 'mixte', 'is_active' => true])->assertSessionHasErrors('sector');
    expect($establishment->fresh()->sector)->toBe(EstablishmentSector::Private);
});

// Academic year BAC limit
it('records the BAC year limit of the scholarship rule on the academic year', function (mixed $value, ?int $expected, bool $valid) {
    $response = $this->actingAs($this->admin)->post('/references/academic-years', ['name' => '2030-2031', 'scholarship_max_bac_year' => $value, 'is_active' => true]);
    if (! $valid) {
        $response->assertSessionHasErrors('scholarship_max_bac_year');
        expect(AcademicYear::query()->where('name', '2030-2031')->exists())->toBeFalse();

        return;
    }
    $response->assertSessionHasNoErrors()->assertRedirect();
    expect(AcademicYear::query()->where('name', '2030-2031')->sole()->scholarship_max_bac_year)->toBe($expected);
})->with([
    'set' => [2025, 2025, true],
    'empty' => [null, null, true],
    'too old' => [1980, null, false],
    'not a year' => ['deux mille', null, false],
]);
