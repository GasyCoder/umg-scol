<?php

use App\Models\Activity;
use App\Models\User;
use App\Support\Permissions\PermissionCatalogue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('superadmin');
});
function roleNames(string $role): array
{
    return Role::findByName($role)->permissions()->pluck('name')->sort()->values()->all();
}
function permissionSave(TestCase $test, string $role, array $names): TestResponse
{
    return $test->put('/permissions/roles/'.$role, ['permissions' => $names, 'version' => PermissionCatalogue::version(roleNames($role))]);
}
it('serves a fixed role matrix to authorized personnel', function () {
    $this->actingAs($this->admin)->get('/permissions')->assertInertia(fn (Assert $page) => $page->component('permissions/index')->has('roles', 4)->has('catalogue', count(config('access.permissions'))));
});
it('hides the developer role from everyone but developers, and never lets them change it', function () {
    $this->actingAs($this->admin)->get('/permissions')->assertInertia(fn (Assert $page) => $page->where('roles', fn ($roles) => ! collect($roles)->contains('name', 'developer')));
    permissionSave($this, 'developer', [])->assertNotFound();
    expect(roleNames('developer'))->toBe(collect(config('access.roles.developer'))->sort()->values()->all());
    $developer = User::factory()->create()->assignRole('developer');
    $this->actingAs($developer)->get('/permissions')->assertInertia(fn (Assert $page) => $page->has('roles', 5));
});
it('denies operational roles and students by default', function (string $role) {
    $user = User::factory()->create()->assignRole($role);
    $this->actingAs($user)->get('/permissions')->assertForbidden();
    permissionSave($this, 'developer', [])->assertForbidden();
})->with(['central_scholarship_office', 'establishment', 'student']);
it('keeps every existing role assignment when seeding again', function () {
    $role = Role::findByName('developer');
    $role->revokePermissionTo('levels.manage');
    Role::findByName('central_scholarship_office')->givePermissionTo('levels.manage');
    $before = roleNames('developer');
    $central = roleNames('central_scholarship_office');
    $this->seed(RolesAndPermissionsSeeder::class);
    expect(roleNames('developer'))->toBe($before)->and(roleNames('central_scholarship_office'))->toBe($central);
});
it('grants newly introduced catalogue permissions only to default roles', function () {
    Permission::findByName('trash.purge')->delete();
    $this->seed(RolesAndPermissionsSeeder::class);
    expect(Role::findByName('superadmin')->hasPermissionTo('trash.purge'))->toBeTrue()->and(Role::findByName('developer')->hasPermissionTo('trash.purge'))->toBeTrue()->and(Role::findByName('establishment')->hasPermissionTo('trash.purge'))->toBeFalse();
});
it('initializes new roles and fresh databases using the documented defaults', function () {
    foreach (config('access.roles') as $role => $names) {
        sort($names);
        expect(roleNames($role))->toBe($names);
    }
    Role::findByName('developer')->delete();
    $this->seed(RolesAndPermissionsSeeder::class);
    $expected = config('access.roles.developer');
    sort($expected);
    expect(roleNames('developer'))->toBe($expected);
});
it('grants and revokes rights immediately through the server', function () {
    $central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->actingAs($central)->get('/references/levels')->assertForbidden();
    $this->actingAs($this->admin);
    permissionSave($this, 'central_scholarship_office', [...roleNames('central_scholarship_office'), 'levels.manage'])->assertSessionHasNoErrors();
    $this->actingAs($central->fresh())->get('/references/levels')->assertOk();
    $this->actingAs($this->admin);
    permissionSave($this, 'central_scholarship_office', array_values(array_diff(roleNames('central_scholarship_office'), ['levels.manage'])))->assertSessionHasNoErrors();
    $this->actingAs($central->fresh())->get('/references/levels')->assertForbidden();
});
it('protects all three superadmin recovery permissions', function (string $permission) {
    $this->actingAs($this->admin);
    permissionSave($this, 'superadmin', array_values(array_diff(roleNames('superadmin'), [$permission])))->assertSessionHasErrors('permissions');
    expect(Role::findByName('superadmin')->hasPermissionTo($permission))->toBeTrue();
})->with(PermissionCatalogue::PROTECTED);
it('refuses internal or unknown permissions for the student role', function (string $permission) {
    $this->actingAs($this->admin);
    permissionSave($this, 'student', [...roleNames('student'), $permission])->assertSessionHasErrors();
})->with(['permissions.manage', 'trash.manage', 'not.in.catalogue']);
it('does not allow adding or changing an undocumented role', function () {
    $this->actingAs($this->admin)->put('/permissions/roles/other', ['permissions' => [], 'version' => str_repeat('a', 64)])->assertNotFound();
    expect(Role::count())->toBe(5);
});
it('detects a stale matrix and keeps the newer assignment', function () {
    $version = PermissionCatalogue::version(roleNames('central_scholarship_office'));
    Role::findByName('central_scholarship_office')->revokePermissionTo('scholarships.export');
    $this->actingAs($this->admin)->put('/permissions/roles/central_scholarship_office', ['permissions' => config('access.roles.central_scholarship_office'), 'version' => $version])->assertSessionHasErrors('permissions');
    expect(Role::findByName('central_scholarship_office')->hasPermissionTo('scholarships.export'))->toBeFalse();
});
it('audits grants and revocations with the acting account', function () {
    $this->actingAs($this->admin);
    permissionSave($this, 'central_scholarship_office', array_values(array_diff(roleNames('central_scholarship_office'), ['scholarships.export'])))->assertSessionHasNoErrors();
    $log = Activity::where('event', 'role_permissions_updated')->sole();
    expect($log->causer_id)->toBe($this->admin->id)->and($log->properties['revoked_permissions'])->toBe(['scholarships.export'])->and($log->properties['role_name'])->toBe('central_scholarship_office');
    expect(Activity::where('event', 'permissions.detached')->where('causer_id', $this->admin->id)->exists())->toBeTrue();
});
