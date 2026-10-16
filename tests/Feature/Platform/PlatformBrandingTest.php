<?php

use App\Models\Activity;
use App\Models\User;
use App\Support\Branding;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake(Branding::DISK);
    Cache::flush();
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

function brandingPayload(array $overrides = []): array
{
    return ['_method' => 'put', 'institution_name' => 'Université de Mahajanga', 'scol_name' => 'Scolarité UMG', 'bourse_name' => 'Bourses UMG', 'support_email' => 'support@umg.test', ...$overrides];
}

it('uses safe defaults until the identity is configured', function () {
    expect(Branding::get())->toMatchArray(['institution_name' => 'Université de Mahajanga', 'scol_name' => config('app.name'), 'bourse_name' => config('app.name'), 'logo_url' => null, 'favicon_url' => null]);
    $this->actingAs($this->admin)->get('/settings/platform')->assertOk()->assertInertia(fn (Assert $page) => $page->component('settings/platform')
        ->where('values.scol_name', '')->where('defaults.scol_name', config('app.name')));
    $this->get('/branding/logo')->assertNotFound();
});

it('saves names, logo and favicon and shows them on both portals', function () {
    $this->actingAs($this->admin)->post('/settings/platform', brandingPayload([
        'logo' => UploadedFile::fake()->image('logo.png', 300, 120),
        'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
    ]))->assertSessionHasNoErrors()->assertRedirect('/settings/platform');

    $branding = Branding::get();
    expect($branding['scol_name'])->toBe('Scolarité UMG')->and($branding['bourse_name'])->toBe('Bourses UMG')->and($branding['logo_url'])->toStartWith('/branding/logo?v=')
        ->and($branding['favicon_url'])->toStartWith('/branding/favicon?v=');
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('name', 'Scolarité UMG')->where('branding.logo_url', $branding['logo_url']));
    expect(Activity::query()->where('event', 'branding_updated')->sole()->properties['changed_fields'])->toContain('scol_name', 'logo_path', 'favicon_path');

    // Public assets on both portals: sign-in screens and favicons are shown before login.
    auth()->forgetGuards();
    $this->flushSession();
    $this->get('/branding/logo')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('http://'.config('portals.bourse_domain').'/branding/favicon')->assertOk();
    $bourse = $this->get('http://'.config('portals.bourse_domain').'/')->assertOk();
    expect($bourse->getContent())->toContain('<title>Bourses UMG | Université de Mahajanga</title>')->toContain('href="'.$branding['favicon_url'].'"')->toContain('data-branding=');
    expect($this->get('/login')->getContent())->toContain('href="'.$branding['favicon_url'].'"');
});

it('replaces and removes files, deleting the previous ones after commit', function () {
    $this->actingAs($this->admin)->post('/settings/platform', brandingPayload(['logo' => UploadedFile::fake()->image('a.png', 100, 100)]));
    $first = Branding::raw()['logo_path'];
    $this->post('/settings/platform', brandingPayload(['logo' => UploadedFile::fake()->image('b.webp', 100, 100)]))->assertSessionHasNoErrors();
    $second = Branding::raw()['logo_path'];
    Storage::disk(Branding::DISK)->assertMissing($first);
    Storage::disk(Branding::DISK)->assertExists($second);
    $this->post('/settings/platform', brandingPayload())->assertSessionHasNoErrors();
    expect(Branding::raw()['logo_path'])->toBe($second);
    $this->post('/settings/platform', brandingPayload(['remove_logo' => true]))->assertSessionHasNoErrors();
    expect(Branding::raw()['logo_path'])->toBeNull();
    Storage::disk(Branding::DISK)->assertMissing($second);
});

it('restores the defaults when names are cleared', function () {
    $this->actingAs($this->admin)->post('/settings/platform', brandingPayload());
    $this->post('/settings/platform', brandingPayload(['scol_name' => '  ', 'bourse_name' => '', 'support_email' => '']))->assertSessionHasNoErrors();
    expect(Branding::get()['scol_name'])->toBe(config('app.name'))->and(Branding::get()['support_email'])->toBeNull();
});

it('rejects unsafe or badly sized files', function (array $payload, string $field) {
    $this->actingAs($this->admin)->post('/settings/platform', brandingPayload($payload))->assertSessionHasErrors($field);
    expect(Storage::disk(Branding::DISK)->allFiles())->toBe([]);
})->with([
    'svg logo' => [['logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')], 'logo'],
    'big logo' => [['logo' => UploadedFile::fake()->image('logo.png', 200, 200)->size(2048)], 'logo'],
    'jpg favicon' => [['favicon' => UploadedFile::fake()->image('fav.jpg', 64, 64)], 'favicon'],
    'non square favicon' => [['favicon' => UploadedFile::fake()->image('fav.png', 64, 32)], 'favicon'],
    'bad email' => [['support_email' => 'pas-un-email'], 'support_email'],
]);

it('reserves the platform identity to its managers', function (string $role) {
    $user = User::factory()->create()->assignRole($role);
    $this->actingAs($user)->get('/settings/platform')->assertForbidden();
    $this->actingAs($user)->post('/settings/platform', brandingPayload())->assertForbidden();
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('auth.canManageBranding', false));
})->with(['central_scholarship_office']);
