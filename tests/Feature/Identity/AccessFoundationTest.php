<?php

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates exactly the five documented roles and the complete permission catalogue', function () {
    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(['central_scholarship_office', 'developer', 'establishment', 'student', 'superadmin']);
    expect(Permission::query()->count())->toBe(36);

    foreach (config('access.permissions') as $permission) {
        expect(Permission::findByName($permission, 'web'))->not->toBeNull();
    }
});

it('grants all functional permissions explicitly to privileged roles', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    foreach (config('access.permissions') as $permission) {
        expect($user->can($permission))->toBeTrue();
    }
})->with(['superadmin', 'developer']);

it('restricts operational roles from global administration', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    foreach (['users.manage', 'roles.manage', 'permissions.manage', 'establishments.manage', 'audits.export'] as $permission) {
        expect($user->can($permission))->toBeFalse();
    }
})->with(['establishment', 'student', 'central_scholarship_office']);

it('gives central office review access without official identity editing', function () {
    $user = User::factory()->create()->assignRole('central_scholarship_office');

    expect($user->can('students.viewAny'))->toBeTrue()
        ->and($user->can('scholarships.validate'))->toBeTrue()
        ->and($user->can('scholarships.reject'))->toBeTrue()
        ->and($user->can('students.update'))->toBeFalse();
});

it('gives establishment management access without scholarship decisions', function () {
    $user = User::factory()->create()->assignRole('establishment');

    expect($user->can('students.update'))->toBeTrue()
        ->and($user->can('imports.commit'))->toBeTrue()
        ->and($user->can('corrections.review'))->toBeTrue()
        ->and($user->can('scholarships.validate'))->toBeFalse();
});

it('limits student permissions to consultation and correction requests', function () {
    $user = User::factory()->create()->assignRole('student');

    expect($user->can('students.view'))->toBeTrue()
        ->and($user->can('corrections.create'))->toBeTrue();

    foreach (['students.viewAny', 'students.create', 'students.update', 'students.export', 'corrections.review', 'imports.commit'] as $permission) {
        expect($user->can($permission))->toBeFalse();
    }
});

it('does not bypass permissions for privileged roles', function () {
    Permission::findOrCreate('future.unassigned', 'web');
    $user = User::factory()->create()->assignRole('superadmin');
    expect($user->can('future.unassigned'))->toBeFalse();
});

it('seeds idempotently without duplicates or redundant audit records', function () {
    $counts = [Role::count(), Permission::count(), Activity::count()];
    $pivots = DB::table('role_has_permissions')->count();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect([Role::count(), Permission::count(), Activity::count()])->toBe($counts)
        ->and(DB::table('role_has_permissions')->count())->toBe($pivots);
});
