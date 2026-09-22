<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_log_in_with_correct_credentials(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret-password')]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_an_admin_cannot_log_in_with_the_wrong_password(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret-password')]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_a_regular_user_session_does_not_grant_admin_access(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_visiting_the_admin_login_page_while_already_authenticated_redirects_to_the_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.login'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_visiting_the_dashboard_while_unauthenticated_redirects_to_the_admin_login_page(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_an_admin_can_log_out(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_the_regular_user_login_and_dashboard_flow_still_works_unaffected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
    }
}
