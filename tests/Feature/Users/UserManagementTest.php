<?php

use App\Actions\Users\SaveUserPermissions;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\User;
use App\Notifications\InternalAccountInvitation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create(['name' => 'Admin Principal'])->assignRole('superadmin');
    $this->establishment = Establishment::factory()->create(['is_active' => true]);
});

function internalUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes)->assignRole($role);
    if ($role === 'establishment' && ! array_key_exists('establishment_id', $attributes)) {
        $user->forceFill(['establishment_id' => Establishment::factory()->create(['is_active' => true])->id])->save();
    }

    return $user;
}
function rightsPayload(User $user, array $granted, array $denied): array
{
    return ['granted' => $granted, 'denied' => $denied, 'version' => SaveUserPermissions::version($user)];
}

// Access
it('lists internal accounts for administrators only', function () {
    $agent = internalUser('central_scholarship_office', ['name' => 'Agent Scolarité']);
    User::factory()->create(['name' => 'Étudiant Portail'])->assignRole('student');
    $this->actingAs($this->admin)->get('/users')->assertOk()->assertInertia(fn (Assert $page) => $page->component('users/index')
        ->has('users.data', 2)->where('stats.total', 2)->where('users.data.0.name', 'Admin Principal')->where('users.data.1.uuid', $agent->uuid));
    foreach (['central_scholarship_office', 'establishment'] as $role) {
        $this->actingAs(internalUser($role))->get('/users')->assertForbidden();
    }
});
it('keeps establishment accounts out of account management even when granted the permission', function () {
    $staff = internalUser('establishment');
    $staff->givePermissionTo('users.manage');
    $this->actingAs($staff)->get('/users')->assertForbidden();
});
it('hides developer accounts from non-developers and lets only developers assign that role', function () {
    $developer = internalUser('developer');
    $this->actingAs($this->admin)->get('/users')->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->has('roles', 3));
    $this->actingAs($this->admin)->get('/users/'.$developer->uuid)->assertForbidden();
    $this->actingAs($this->admin)->post('/users', ['name' => 'Dev', 'email' => 'dev@example.test', 'role' => 'developer'])->assertSessionHasErrors('role');
    $this->actingAs($developer)->get('/users')->assertInertia(fn (Assert $page) => $page->has('users.data', 2)->has('roles', 4));
});
it('never manages student accounts and answers 404 for numeric ids', function () {
    $student = User::factory()->create()->assignRole('student');
    $this->actingAs($this->admin)->get('/users/'.$student->uuid)->assertForbidden();
    $this->actingAs($this->admin)->get('/users/'.$this->admin->id)->assertNotFound();
});

// Creation and update
it('creates an account with one role and sends an invitation to choose the password', function () {
    Notification::fake();
    $this->actingAs($this->admin)->post('/users', ['name' => ' Rasoa Hery ', 'email' => 'Rasoa@Example.test', 'role' => 'establishment', 'establishment_id' => $this->establishment->id])
        ->assertRedirect()->assertSessionHasNoErrors();
    $user = User::query()->where('email', 'rasoa@example.test')->sole();
    expect($user->name)->toBe('Rasoa Hery')->and($user->getRoleNames()->all())->toBe(['establishment'])->and($user->establishment_id)->toBe($this->establishment->id)
        ->and($user->is_active)->toBeTrue()->and($user->email_verified_at)->not->toBeNull();
    Notification::assertSentTo($user, InternalAccountInvitation::class);
    expect(Activity::query()->where('event', 'user_created')->where('subject_id', $user->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'user_invitation_sent')->exists())->toBeTrue();
});
it('validates role, unique email and the establishment of establishment accounts', function () {
    $this->actingAs($this->admin)->post('/users', ['name' => 'X', 'email' => $this->admin->email, 'role' => 'establishment'])
        ->assertSessionHasErrors(['email', 'establishment_id']);
    $inactive = Establishment::factory()->create(['is_active' => false]);
    $this->actingAs($this->admin)->post('/users', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'establishment', 'establishment_id' => $inactive->id])->assertSessionHasErrors('establishment_id');
    $this->actingAs($this->admin)->post('/users', ['name' => 'X', 'email' => 'y@example.test', 'role' => 'student'])->assertSessionHasErrors('role');
});
it('changes the role, drops the establishment of a global role and realigns adjustments', function () {
    $user = internalUser('establishment');
    $user->givePermissionTo('scholarships.view');
    $user->deniedPermissions()->attach(Permission::findByName('imports.commit')->id);
    $this->actingAs($this->admin)->put('/users/'.$user->uuid, ['name' => $user->name, 'email' => $user->email, 'role' => 'central_scholarship_office', 'establishment_id' => $user->establishment_id])
        ->assertRedirect('/users/'.$user->uuid);
    $user->refresh();
    expect($user->getRoleNames()->all())->toBe(['central_scholarship_office'])->and($user->establishment_id)->toBeNull()
        ->and(SaveUserPermissions::granted($user))->toBe([])->and(SaveUserPermissions::denied($user))->toBe([]);
    expect(Activity::query()->where('event', 'user_updated')->latest('id')->first()->properties['previous_role'])->toBe('establishment');
});
it('forbids acting on your own account', function () {
    $this->actingAs($this->admin)->put('/users/'.$this->admin->uuid, ['name' => 'Moi', 'email' => $this->admin->email, 'role' => 'central_scholarship_office'])->assertForbidden();
    $this->actingAs($this->admin)->patch('/users/'.$this->admin->uuid.'/status', ['active' => false])->assertForbidden();
    expect($this->admin->fresh()->hasRole('superadmin'))->toBeTrue()->and($this->admin->fresh()->is_active)->toBeTrue();
});
it('keeps at least one active superadmin', function () {
    $second = internalUser('superadmin');
    $this->actingAs($second)->patch('/users/'.$this->admin->uuid.'/status', ['active' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->admin->fresh()->is_active)->toBeFalse();
    // A developer may manage accounts but cannot suspend or demote the last active superadmin.
    $developer = internalUser('developer');
    $this->actingAs($developer)->patch('/users/'.$second->uuid.'/status', ['active' => false])->assertSessionHasErrors('role');
    $this->actingAs($developer)->put('/users/'.$second->uuid, ['name' => $second->name, 'email' => $second->email, 'role' => 'central_scholarship_office'])->assertSessionHasErrors('role');
    expect($second->fresh()->is_active)->toBeTrue()->and($second->fresh()->hasRole('superadmin'))->toBeTrue();
});

// Suspension
it('blocks a suspended account at login and ends its open sessions', function () {
    config(['session.driver' => 'database']);
    $user = internalUser('central_scholarship_office', ['password' => 'Secret-password-2026!']);
    DB::table('sessions')->insert(['id' => 'open-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $this->actingAs($this->admin)->patch('/users/'.$user->uuid.'/status', ['active' => false])->assertRedirect();
    expect($user->fresh()->is_active)->toBeFalse()->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'user_suspended')->exists())->toBeTrue();

    $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});
it('tells only the holder of the right password that the account is suspended', function () {
    $user = internalUser('central_scholarship_office', ['password' => 'Secret-password-2026!']);
    $user->forceFill(['is_active' => false])->save();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Secret-password-2026!'])->assertSessionHasErrors(['email' => 'Ce compte est suspendu. Contactez un administrateur.']);
    $this->assertGuest();
});

// Rights
it('adds and withdraws rights per account on top of the role defaults', function () {
    $user = internalUser('central_scholarship_office');
    expect($user->can('students.export'))->toBeTrue()->and($user->can('audits.view'))->toBeFalse();
    $this->actingAs($this->admin)->put('/users/'.$user->uuid.'/permissions', rightsPayload($user, ['audits.view'], ['students.export']))->assertRedirect('/users/'.$user->uuid);
    $user = User::query()->find($user->id);
    expect($user->can('audits.view'))->toBeTrue()->and($user->can('students.export'))->toBeFalse()
        ->and($user->getAllPermissions()->pluck('name')->contains('students.export'))->toBeFalse()
        ->and($user->getAllPermissions()->pluck('name')->contains('audits.view'))->toBeTrue();
    $log = Activity::query()->where('event', 'user_permissions_updated')->sole();
    expect($log->properties['granted_permissions'])->toBe(['audits.view'])->and($log->properties['denied_permissions'])->toBe(['students.export']);
    // The withdrawal is effective on the real pages and in the shared menu rights.
    $this->actingAs($user)->get('/audits')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.permissions', fn ($names) => ! collect($names)->contains('students.export')));
});
it('restores the role defaults when adjustments are cleared', function () {
    $user = internalUser('central_scholarship_office');
    $this->actingAs($this->admin)->put('/users/'.$user->uuid.'/permissions', rightsPayload($user, ['audits.view'], ['students.export']));
    $user->refresh();
    $this->actingAs($this->admin)->put('/users/'.$user->uuid.'/permissions', rightsPayload($user, [], []))->assertRedirect();
    $user = User::query()->find($user->id);
    expect($user->can('students.export'))->toBeTrue()->and($user->can('audits.view'))->toBeFalse();
});
it('rejects incoherent, stale or protected adjustments', function () {
    $user = internalUser('central_scholarship_office');
    $this->actingAs($this->admin)->put('/users/'.$user->uuid.'/permissions', rightsPayload($user, ['students.export'], []))->assertSessionHasErrors('permissions');
    $this->actingAs($this->admin)->put('/users/'.$user->uuid.'/permissions', rightsPayload($user, [], ['audits.view']))->assertSessionHasErrors('permissions');
    $this->actingAs($this->admin)->put('/users/'.$user->uuid.'/permissions', ['granted' => [], 'denied' => [], 'version' => str_repeat('a', 64)])->assertSessionHasErrors('permissions');
    $this->actingAs($this->admin)->put('/users/'.$user->uuid.'/permissions', rightsPayload($user, ['unknown.right'], []))->assertSessionHasErrors('granted.0');
    $other = internalUser('superadmin');
    $this->actingAs($this->admin)->put('/users/'.$other->uuid.'/permissions', rightsPayload($other, [], ['users.manage']))->assertSessionHasErrors('permissions');
    $this->actingAs($this->admin)->put('/users/'.$this->admin->uuid.'/permissions', rightsPayload($this->admin, [], ['audits.view']))->assertForbidden();
});
it('needs permissions.manage to adjust rights', function () {
    $manager = internalUser('superadmin');
    $manager->deniedPermissions()->attach(Permission::findByName('permissions.manage')->id);
    $user = internalUser('central_scholarship_office');
    $this->actingAs($manager)->get('/users/'.$user->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.managePermissions', false));
    $this->actingAs($manager)->put('/users/'.$user->uuid.'/permissions', rightsPayload($user, ['audits.view'], []))->assertForbidden();
});
it('shows the account card with its rights and recent activity', function () {
    $user = internalUser('establishment');
    $this->actingAs($this->admin)->get('/users/'.$user->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page->component('users/show')
        ->where('user.role', 'establishment')->where('user.establishment.id', $user->establishment_id)->has('catalogue', count(config('access.permissions')))
        ->where('rights.granted', [])->where('rights.denied', [])->has('rights.version')->where('can.update', true)->has('activity'));
});
it('resends an invitation to active accounts only', function () {
    Notification::fake();
    $user = internalUser('central_scholarship_office');
    $this->actingAs($this->admin)->post('/users/'.$user->uuid.'/invitation')->assertRedirect();
    Notification::assertSentTo($user, InternalAccountInvitation::class);
    $user->forceFill(['is_active' => false])->save();
    $this->actingAs($this->admin)->post('/users/'.$user->uuid.'/invitation')->assertStatus(422);
});
