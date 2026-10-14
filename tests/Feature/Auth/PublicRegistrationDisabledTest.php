<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('does not expose Fortify registration routes', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse()
        ->and(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

it('rejects public administrative account creation on every current host', function (string $host) {
    $this->get('http://'.$host.'/register')->assertNotFound();
    $this->post('http://'.$host.'/register', [
        'name' => 'Unauthorized Administrator', 'email' => 'unauthorized@example.test',
        'password' => 'Test-Password-47!', 'password_confirmation' => 'Test-Password-47!',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with(['scol.umg.test', 'bourse.umg.test', '127.0.0.1']);
