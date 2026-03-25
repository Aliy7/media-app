<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    // --- store() ---

    public function test_authenticated_user_can_upload_valid_image(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($user)->post('/media', ['file' => $file]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'status']);
        $this->assertDatabaseHas('media', ['user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_upload_returns_pending_status(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($user)->post('/media', ['file' => $file]);

        $response->assertJson(['status' => 'pending']);
    }

    public function test_uploaded_file_is_stored_on_disk(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $this->actingAs($user)->post('/media', ['file' => $file]);

        $media = Media::first();
        Storage::disk('media')->assertExists($media->stored_filename);
    }

    public function test_unauthenticated_upload_redirects_to_login(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->post('/media', ['file' => $file]);

        $response->assertRedirect('/login');
    }

    public function test_invalid_mime_type_returns_422(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf');

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_oversized_file_returns_422(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('big.jpg', 800, 600)->size(11264);

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_missing_file_returns_422(): void
    {
        $user = User::factory()->create();

        // postJson sets Accept: application/json so validation errors return 422 not redirect
        $response = $this->actingAs($user)->postJson('/media', []);

        $response->assertStatus(422);
    }

    // --- show() ---

    public function test_owner_can_view_their_media(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/media/{$media->uuid}");

        $response->assertOk();
        $response->assertJsonFragment(['uuid' => $media->uuid]);
    }

    public function test_other_user_cannot_view_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->get("/media/{$media->uuid}");

        $response->assertForbidden();
    }

    // --- destroy() ---

    public function test_owner_can_delete_their_media(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/media/{$media->uuid}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_other_user_cannot_delete_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->delete("/media/{$media->uuid}");

        $response->assertForbidden();
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }
}
