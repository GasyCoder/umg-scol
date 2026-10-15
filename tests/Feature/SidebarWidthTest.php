<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shares the splitter width from its cookie, clamped to the allowed range', function (?string $cookie, int $expected) {
    $this->withoutVite();
    $request = $this->actingAs(User::factory()->internal()->create());
    if ($cookie !== null) {
        $request = $request->withUnencryptedCookie('sidebar_width', $cookie);
    }
    $request->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('sidebarWidth', $expected));
})->with([
    'default' => [null, 256],
    'chosen' => ['320', 320],
    'too narrow' => ['10', 224],
    'too wide' => ['5000', 400],
    'garbage' => ['abc', 256],
]);
