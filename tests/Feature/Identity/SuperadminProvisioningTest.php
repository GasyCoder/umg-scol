<?php

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['provisioning.superadmin' => ['name' => null, 'email' => null, 'password' => null]]);
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('does not create a default account without explicit configuration', function () {
    $this->seed(DatabaseSeeder::class);
    expect(User::count())->toBe(0);
});

it('provisions an audited superadmin without exposing or resetting its password', function () {
    config(['provisioning.superadmin' => [
        'name' => 'Initial Administrator', 'email' => 'admin@example.test',
        'password' => 'Test-Only-Strong-Password-47!',
    ]]);
    $this->seed(DatabaseSeeder::class);
    $user = User::sole();
    $hash = $user->password;
    $count = Activity::count();

    expect($user->hasRole('superadmin'))->toBeTrue()
        ->and(Hash::check('Test-Only-Strong-Password-47!', $hash))->toBeTrue()
        ->and(Activity::query()->where('subject_id', $user->id)->where('event', 'created')->exists())->toBeTrue();

    config(['provisioning.superadmin.password' => 'A-Different-Test-Password-47!']);
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and($user->fresh()->password)->toBe($hash)
        ->and(Activity::count())->toBe($count)
        ->and(Activity::all()->toJson())->not->toContain('Test-Only-Strong-Password-47!', $hash);
});

it('can grant an existing account without replacing its identity or credentials', function () {
    $user = User::factory()->create(['email' => 'existing@example.test']);
    $hash = $user->password;
    $name = $user->name;
    config(['provisioning.superadmin' => ['name' => null, 'email' => $user->email, 'password' => null]]);

    $this->seed(SuperadminSeeder::class);

    expect($user->fresh()->hasRole('superadmin'))->toBeTrue()
        ->and($user->fresh()->name)->toBe($name)
        ->and($user->fresh()->password)->toBe($hash);
});

it('rejects incomplete configuration without creating an account', function () {
    config(['provisioning.superadmin.email' => 'admin@example.test']);

    expect(fn () => $this->seed(SuperadminSeeder::class))->toThrow(RuntimeException::class);
    expect(User::count())->toBe(0);
});

it('rejects a weak password without echoing it', function () {
    config(['provisioning.superadmin' => ['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'weak-secret']]);

    try {
        $this->seed(SuperadminSeeder::class);
        $this->fail('The weak password should be rejected.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->not->toContain('weak-secret');
    }

    expect(User::count())->toBe(0);
});
