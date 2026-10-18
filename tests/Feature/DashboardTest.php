<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->internal()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_users_without_the_dashboard_permission_land_on_another_module()
    {
        $user = User::factory()->internal()->create();
        $user->deniedPermissions()->attach(Permission::findByName('dashboard.view'));
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect();
    }
}
