<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_view_their_own_profile(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/profile/edit')
            ->where('profile.name', $admin->name)
            ->where('profile.email', $admin->email)
            ->missing('profile.password'));
    }

    public function test_an_admin_can_update_their_own_name_and_email(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated-admin@example.com',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.profile.edit'));

        $admin->refresh();
        $this->assertSame('Updated Name', $admin->name);
        $this->assertSame('updated-admin@example.com', $admin->email);
    }

    public function test_an_admin_can_keep_their_current_email(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.profile.update'), [
            'name' => 'Same Email',
            'email' => $admin->email,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.profile.edit'));
        $this->assertSame('Same Email', $admin->refresh()->name);
    }

    public function test_an_admin_cannot_take_another_admins_email(): void
    {
        $admin = Admin::factory()->create();
        $other = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->from(route('admin.profile.edit'))->patch(route('admin.profile.update'), [
            'name' => $admin->name,
            'email' => $other->email,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame($admin->email, $admin->refresh()->email);
    }

    public function test_updating_a_profile_does_not_change_another_admin(): void
    {
        $admin = Admin::factory()->create();
        $other = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->patch(route('admin.profile.update'), [
            'name' => 'Only Me',
            'email' => 'only-me@example.com',
        ]);

        $other->refresh();
        $this->assertNotSame('Only Me', $other->name);
        $this->assertNotSame('only-me@example.com', $other->email);
    }

    public function test_an_admin_can_change_their_password(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('current-password')]);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.profile.password'), [
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.profile.edit'));
        $this->assertTrue(Hash::check('new-password', $admin->refresh()->password));
    }

    public function test_a_wrong_current_password_does_not_change_the_password(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('current-password')]);

        $response = $this->actingAs($admin, 'admin')->from(route('admin.profile.edit'))->put(route('admin.profile.password'), [
            'current_password' => 'not-the-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('current-password', $admin->refresh()->password));
    }

    public function test_a_guest_is_redirected_to_the_admin_login(): void
    {
        $this->get(route('admin.profile.edit'))->assertRedirect(route('admin.login'));
    }

    public function test_a_regular_user_cannot_open_the_admin_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.profile.edit'))->assertRedirect(route('admin.login'));
    }
}
