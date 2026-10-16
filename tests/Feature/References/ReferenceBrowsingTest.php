<?php

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
    $this->edsp = Establishment::factory()->create(['code' => 'EDSP', 'name' => 'Ecole de Droit']);
    $this->fst = Establishment::factory()->create(['code' => 'FST', 'name' => 'Faculté des Sciences']);
    $law = Mention::factory()->create(['establishment_id' => $this->edsp->id, 'name' => 'Droit']);
    $economy = Mention::factory()->create(['establishment_id' => $this->edsp->id, 'name' => 'Économie']);
    Mention::factory()->create(['establishment_id' => $this->fst->id, 'name' => 'Physique']);
    Program::factory()->create(['establishment_id' => $this->edsp->id, 'mention_id' => $law->id, 'name' => 'Tronc Commun']);
    Program::factory()->create(['establishment_id' => $this->edsp->id, 'mention_id' => $economy->id, 'name' => 'Gestion']);
    Program::factory()->create(['establishment_id' => $this->edsp->id, 'mention_id' => $law->id, 'name' => 'Droit Privé']);
    Program::factory()->create(['establishment_id' => $this->edsp->id, 'mention_id' => null, 'name' => 'Préparatoire']);
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

it('starts the mention and program lists with one card per establishment for a global actor', function (string $kind) {
    $this->actingAs($this->admin)->get('/references/'.$kind)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('establishmentCards', 2)
        ->where('establishmentCards.0.code', 'EDSP')->where('establishmentCards.0.mentions_count', 2)->where('establishmentCards.0.programs_count', 4)
        ->where('establishmentCards.1.code', 'FST')->where('establishmentCards.1.mentions_count', 1));
    $this->get('/references/'.$kind.'?establishment_id='.$this->edsp->id)->assertInertia(fn (Assert $page) => $page->where('establishmentCards', null));
    $this->get('/references/'.$kind.'?search=Droit')->assertInertia(fn (Assert $page) => $page->where('establishmentCards', null));
})->with(['mentions', 'programs']);

it('lists the programs of an establishment grouped by mention, programs without mention last', function () {
    $this->actingAs($this->admin)->get('/references/programs?establishment_id='.$this->edsp->id)->assertInertia(fn (Assert $page) => $page
        ->where('records.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Droit Privé', 'Tronc Commun', 'Gestion', 'Préparatoire']));
});

it('opens an establishment account directly on its own list', function () {
    $user = User::factory()->create(['establishment_id' => $this->edsp->id])->assignRole('establishment');
    $user->givePermissionTo('programs.manage');

    $this->actingAs($user)->get('/references/mentions')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('establishmentCards', null)->where('records.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Droit', 'Économie']));
});
