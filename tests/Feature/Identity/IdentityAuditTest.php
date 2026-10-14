<?php

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('audits successful authentication and logout without credentials', function () {
    $user = User::factory()->internal()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->post(route('logout'))->assertRedirect(route('home'));

    $activities = Activity::query()->where('log_name', 'authentication')->get();
    expect($activities->pluck('event')->all())->toBe(['login', 'logout']);

    foreach ($activities as $activity) {
        expect($activity->causer_id)->toBe($user->id)
            ->and($activity->subject_id)->toBe($user->id)
            ->and($activity->toJson())->not->toContain($user->email, 'password', $user->password);
    }
});

it('records a developer as an ordinary identifiable actor', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $developer = User::factory()->create()->assignRole('developer');
    $subject = User::factory()->create();
    $this->actingAs($developer);
    $subject->update(['name' => 'Updated Identity']);

    $activity = Activity::query()->where('log_name', 'identity')->where('event', 'updated')->sole();
    expect($activity->causer_id)->toBe($developer->id)
        ->and($activity->causer_type)->toBe($developer->getMorphClass())
        ->and($activity->subject_id)->toBe($subject->id)
        ->and($activity->causer->hasRole('developer'))->toBeTrue();
});

it('never records secret attributes or email values during identity changes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->forceFill([
        'name' => 'Changed Name', 'email' => 'private-address@example.test',
        'password' => 'New-Secret-Password-47!', 'remember_token' => 'private-remember-token',
        'two_factor_secret' => encrypt('private-two-factor-secret'),
        'two_factor_recovery_codes' => encrypt('private-recovery-codes'),
    ])->save();

    $activity = Activity::query()->where('event', 'updated')->sole();
    $json = $activity->toJson();

    foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'New-Secret-Password-47!', 'private-remember-token', 'private-address@example.test', $user->password, $user->two_factor_secret] as $secret) {
        expect($json)->not->toContain($secret);
    }

    expect($activity->causer_id)->toBe($user->id)
        ->and($activity->attribute_changes->get('attributes')['name'])->toBe('Changed Name');
});

it('keeps an audit event for a password-only update without its value', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->update(['password' => 'Another-Private-Password-47!']);

    $activity = Activity::query()->where('event', 'updated')->sole();
    expect($activity->causer_id)->toBe($user->id)
        ->and($activity->toJson())->not->toContain('Another-Private-Password-47!', $user->password);
});

it('audits role and permission grants and revocations with their actor', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $developer = User::factory()->create()->assignRole('developer');
    $user = User::factory()->create();
    $this->actingAs($developer);

    $user->assignRole('establishment');
    $user->removeRole('establishment');
    $user->givePermissionTo('audits.view');
    $user->revokePermissionTo('audits.view');

    $activities = Activity::query()->where('log_name', 'access')->where('causer_id', $developer->id)->get();
    expect($activities->pluck('event')->all())->toBe(['roles.attached', 'roles.detached', 'permissions.attached', 'permissions.detached']);

    foreach ($activities as $activity) {
        expect($activity->subject_id)->toBe($user->id)
            ->and($activity->properties->get('ids'))->not->toBeEmpty();
    }
});
