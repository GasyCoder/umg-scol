<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('requires authentication to access settings', function () {
    $this->get('/settings')->assertRedirect(route('login'));
});

it('redirects authenticated navigation to the profile', function (string $method) {
    $this->actingAs(User::factory()->create())
        ->call($method, '/settings')
        ->assertRedirect(route('profile.edit'));
})->with(['GET', 'HEAD']);

it('rejects non-navigation methods on the settings redirect', function (string $method) {
    $this->actingAs(User::factory()->create())
        ->call($method, '/settings')
        ->assertStatus(405);
})->with(['POST', 'PUT', 'PATCH', 'DELETE', 'QUERY']);

it('advertises only navigation methods for options requests', function () {
    $this->options('/settings')
        ->assertOk()
        ->assertHeader('Allow', 'GET,HEAD');
});
