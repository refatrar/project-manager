<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_upload_a_profile_picture(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertNotNull($user->profile_picture);
        Storage::disk('public')->assertExists($user->profile_picture);
        $this->assertStringContainsString($user->profile_picture, (string) $user->avatar);
    }

    public function test_uploading_a_new_picture_deletes_the_previous_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $firstPath = $user->refresh()->profile_picture;

        $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('second.jpg'),
        ]);
        $secondPath = $user->refresh()->profile_picture;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_a_user_can_remove_their_profile_picture(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);
        $path = $user->refresh()->profile_picture;

        $response = $this->actingAs($user)->delete(route('profile.avatar.destroy'));

        $response->assertRedirect(route('profile.edit'));
        $this->assertNull($user->refresh()->profile_picture);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_svg_upload_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
        ]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($user->refresh()->profile_picture);
    }

    public function test_an_oversized_upload_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg')->size(6000),
        ]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($user->refresh()->profile_picture);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('avatar');
        $this->assertNull($user->refresh()->profile_picture);
    }
}
