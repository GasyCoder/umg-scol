<?php

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\User;
use App\Support\Scholarships\DdbLabel;
use App\Support\Scholarships\DdbNumber;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    $this->year = AcademicYear::factory()->create(['name' => '2026-2027', 'is_current' => true, 'is_active' => true]);
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

it('prints the prefix of the DDB pattern with blank digits and the QR code of the student portal', function () {
    $this->actingAs($this->admin)->get('/scholarships/reception/labels')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('scholarships/reception-labels')
            ->where('yearId', $this->year->id)
            ->where('label', ['prefix' => 'DDB-2026-', 'suffix' => '', 'digits' => 6])
            ->where('texts', ['heading' => 'Bourse nationale', 'caption' => 'Numéro DDB', 'hint' => 'Suivi du dossier en ligne', 'footer' => 'Service Scolarité Centrale', 'url' => 'https://bourse.mahajanga-univ.mg', 'urlLabel' => 'bourse.mahajanga-univ.mg'])
            ->where('maxSheets', 20)
            ->where('qr', fn (string $qr) => str_starts_with($qr, 'data:image/svg+xml;base64,') && str_contains(base64_decode(substr($qr, 26)), '<svg')));
});

it('follows the pattern set by the platform, leaving the establishment code blank', function () {
    DdbNumber::put(['pattern' => '{ETAB}/{AU}/{NUM}-B', 'digits' => 4]);

    $this->actingAs($this->admin)->get('/scholarships/reception/labels')
        ->assertInertia(fn (Assert $page) => $page->where('label', ['prefix' => DdbNumber::LABEL_BLANK.'/2026-2027/', 'suffix' => '-B', 'digits' => 4]));
});

it('offers the active academic years, the current one first', function () {
    $next = AcademicYear::factory()->create(['name' => '2027-2028', 'is_active' => true, 'is_current' => false]);
    AcademicYear::factory()->create(['name' => '2020-2021', 'is_active' => false, 'is_current' => false]);

    $this->actingAs($this->admin)->get('/scholarships/reception/labels')
        ->assertInertia(fn (Assert $page) => $page->where('yearId', $this->year->id)->has('years', 2)->where('years.0.is_current', true)->where('years.1.id', $next->id));
    $this->actingAs($this->admin)->get('/scholarships/reception/labels?academic_year_id='.$next->id)
        ->assertInertia(fn (Assert $page) => $page->where('yearId', $next->id)->where('label.prefix', 'DDB-2027-'));
});

it('shows an empty sheet without an active academic year', function () {
    $this->year->update(['is_active' => false]);

    $this->actingAs($this->admin)->get('/scholarships/reception/labels')
        ->assertInertia(fn (Assert $page) => $page->where('yearId', null)->where('label', null));
});

it('is reserved to the holders of scholarships.labels, superadmin only by default', function () {
    $staff = User::factory()->create(['establishment_id' => Establishment::factory()->create()->id])->assignRole('establishment');

    expect(Role::findByName('superadmin')->hasPermissionTo('scholarships.labels'))->toBeTrue()
        ->and(Role::findByName('central_scholarship_office')->hasPermissionTo('scholarships.labels'))->toBeFalse();
    $this->actingAs($this->central)->get('/scholarships/reception/labels')->assertForbidden();
    $this->actingAs($this->central)->get('/scholarships/reception')->assertInertia(fn (Assert $page) => $page
        ->where('auth.scholarships.receive', true)->where('auth.scholarships.labels', false));
    $this->actingAs($staff)->get('/scholarships/reception/labels')->assertForbidden();
    auth()->logout();
    $this->get('/scholarships/reception/labels')->assertRedirect();

    // Granted to the central office through the permission matrix.
    Role::findByName('central_scholarship_office')->givePermissionTo('scholarships.labels');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->central->fresh())->get('/scholarships/reception/labels')->assertOk();
});

it('prints the label texts set by the platform administrator', function () {
    $this->actingAs($this->admin)->put('/settings/platform/labels', [
        'ddb_label_heading' => '  Bourse  d’études ', 'ddb_label_caption' => 'N° de dépôt', 'ddb_label_hint' => '', 'ddb_label_footer' => 'Scolarité Centrale UMG', 'ddb_label_url' => 'https://bourse.umg.mg/',
    ])->assertSessionHasNoErrors()->assertRedirect('/settings/platform');

    $this->actingAs($this->admin)->get('/scholarships/reception/labels')
        ->assertInertia(fn (Assert $page) => $page->where('texts', ['heading' => 'Bourse d’études', 'caption' => 'N° de dépôt', 'hint' => 'Suivi du dossier en ligne', 'footer' => 'Scolarité Centrale UMG', 'url' => 'https://bourse.umg.mg/', 'urlLabel' => 'bourse.umg.mg']));
    $activity = Activity::query()->where('event', 'ddb_labels_updated')->sole();
    expect($activity->causer_id)->toBe($this->admin->id)
        ->and($activity->properties['fields'])->toBe(['ddb_label_heading', 'ddb_label_caption', 'ddb_label_footer', 'ddb_label_url']);

    // Saving the same texts again logs nothing; empty fields restore the defaults.
    $this->actingAs($this->admin)->put('/settings/platform/labels', ['ddb_label_heading' => 'Bourse d’études', 'ddb_label_caption' => 'N° de dépôt', 'ddb_label_footer' => 'Scolarité Centrale UMG', 'ddb_label_url' => 'https://bourse.umg.mg/']);
    expect(Activity::query()->where('event', 'ddb_labels_updated')->count())->toBe(1);
    $this->actingAs($this->admin)->put('/settings/platform/labels', []);
    $this->actingAs($this->admin)->get('/settings/platform')->assertInertia(fn (Assert $page) => $page
        ->where('labels.values.ddb_label_footer', '')->where('labels.defaults.ddb_label_footer', 'Service Scolarité Centrale'));
});

it('rejects label texts too long for the label and links that are not web addresses', function () {
    $this->actingAs($this->admin)->put('/settings/platform/labels', ['ddb_label_footer' => str_repeat('x', 61), 'ddb_label_url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors(['ddb_label_footer', 'ddb_label_url']);
});

it('lets only the platform administrator change the label texts', function () {
    $this->actingAs($this->central)->put('/settings/platform/labels', ['ddb_label_footer' => 'Autre'])->assertForbidden();
    expect(DdbLabel::texts()['footer'])->toBe('Service Scolarité Centrale');
});
