<?php

use App\Models\Establishment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake(Establishment::LOGO_DISK);
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

function establishmentPayload(array $overrides = []): array
{
    return ['code' => 'fst', 'name' => 'Faculté des Sciences', 'type' => 'Faculté', 'is_active' => true, ...$overrides];
}

it('creates an establishment with its sigle, a free type and a logo', function () {
    $this->actingAs($this->admin)->post('/references/establishments', establishmentPayload([
        'type' => 'Centre de recherche', 'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ]))->assertSessionHasNoErrors()->assertRedirect('/references/establishments');
    $establishment = Establishment::query()->sole();
    expect($establishment->code)->toBe('FST')->and($establishment->type)->toBe('Centre de recherche')->and($establishment->logo_path)->toStartWith('establishment-logos/');
    Storage::disk(Establishment::LOGO_DISK)->assertExists($establishment->logo_path);

    $this->get('/references/establishments/'.$establishment->uuid.'/edit')->assertInertia(fn (Assert $page) => $page->component('references/establishments/form')
        ->where('record.logo_url', $establishment->logoUrl())->where('types', ['Faculté', 'Institut', 'École']));
    $this->get('/references/establishments')->assertInertia(fn (Assert $page) => $page->where('records.data.0.logo_url', $establishment->logoUrl()));
    $this->get($establishment->logoUrl())->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('replaces and removes the logo, deleting the previous file after commit', function () {
    $this->actingAs($this->admin)->post('/references/establishments', establishmentPayload(['logo' => UploadedFile::fake()->image('a.png', 100, 100)]));
    $establishment = Establishment::query()->sole();
    $first = $establishment->logo_path;

    $this->post('/references/establishments/'.$establishment->uuid, establishmentPayload(['_method' => 'put', 'logo' => UploadedFile::fake()->image('b.webp', 120, 120)]))->assertSessionHasNoErrors();
    $second = $establishment->fresh()->logo_path;
    expect($second)->not->toBe($first);
    Storage::disk(Establishment::LOGO_DISK)->assertMissing($first);
    Storage::disk(Establishment::LOGO_DISK)->assertExists($second);

    // Saving other fields keeps the logo.
    $this->put('/references/establishments/'.$establishment->uuid, establishmentPayload(['name' => 'FS']))->assertSessionHasNoErrors();
    expect($establishment->fresh()->logo_path)->toBe($second);

    $this->put('/references/establishments/'.$establishment->uuid, establishmentPayload(['remove_logo' => true]))->assertSessionHasNoErrors();
    expect($establishment->fresh()->logo_path)->toBeNull()->and($establishment->fresh()->logoUrl())->toBeNull();
    Storage::disk(Establishment::LOGO_DISK)->assertMissing($second);
    $this->get('/references/establishments/'.$establishment->uuid.'/logo')->assertNotFound();
});

it('rejects non raster, oversized or tiny logos and invalid sigles', function (array $payload, string $field) {
    $this->actingAs($this->admin)->post('/references/establishments', establishmentPayload($payload))->assertSessionHasErrors($field);
    expect(Establishment::query()->count())->toBe(0);
    expect(Storage::disk(Establishment::LOGO_DISK)->allFiles())->toBe([]);
})->with([
    'svg' => [['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')], 'logo'],
    'pdf' => [['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')], 'logo'],
    'too big' => [['logo' => UploadedFile::fake()->image('big.png', 400, 400)->size(2048)], 'logo'],
    'too small' => [['logo' => UploadedFile::fake()->image('tiny.png', 10, 10)], 'logo'],
    'sigle' => [['code' => 'F S T'], 'code'],
]);

it('labels the code as the sigle in validation messages', function () {
    $this->actingAs($this->admin)->post('/references/establishments', establishmentPayload(['code' => '']))
        ->assertSessionHasErrors(['code' => 'Le champ sigle est obligatoire.']);
});

it('serves the logo only to staff allowed to see the establishment', function () {
    $this->actingAs($this->admin)->post('/references/establishments', establishmentPayload(['logo' => UploadedFile::fake()->image('a.png', 100, 100)]));
    $establishment = Establishment::query()->sole();
    $other = User::factory()->create()->assignRole('establishment');
    $other->forceFill(['establishment_id' => Establishment::factory()->create()->id])->save();
    $other->givePermissionTo('establishments.manage');
    $this->actingAs($other)->get($establishment->logoUrl())->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole('central_scholarship_office'))->get($establishment->logoUrl())->assertForbidden();
    auth()->forgetGuards();
});

it('deletes the logo file when the establishment is purged', function () {
    $this->actingAs($this->admin)->post('/references/establishments', establishmentPayload(['logo' => UploadedFile::fake()->image('a.png', 100, 100)]));
    $establishment = Establishment::query()->sole();
    $path = $establishment->logo_path;
    $this->delete('/trash/establishments/'.$establishment->uuid)->assertSessionHasNoErrors();
    Storage::disk(Establishment::LOGO_DISK)->assertExists($path);
    $this->delete('/trash/establishments/'.$establishment->uuid.'/purge')->assertSessionHasNoErrors();
    Storage::disk(Establishment::LOGO_DISK)->assertMissing($path);
});
