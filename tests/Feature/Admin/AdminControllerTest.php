<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_an_admin_account(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.admins.store'), [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $created = Admin::query()->where('email', 'new-admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $created->password));
    }

    public function test_creating_an_admin_with_a_duplicate_email_fails_validation(): void
    {
        $admin = Admin::factory()->create();
        $existing = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.admins.store'), [
            'name' => 'Duplicate',
            'email' => $existing->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_a_regular_user_cannot_access_the_admin_admins_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.admins.index'));

        $response->assertRedirect(route('admin.login'));
    }
}
