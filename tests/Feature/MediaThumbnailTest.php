<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaThumbnailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Queue::fake();
    }

    public function test_owner_can_fetch_thumbnail(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
            'outputs' => ['thumbnail' => 'outputs/uuid_thumbnail.jpg'],
        ]);

        Storage::disk('media')->put('outputs/uuid_thumbnail.jpg', 'fake-image-bytes');

        $response = $this->actingAs($user)->get("/media/{$media->uuid}/thumbnail");

        $response->assertOk();
    }

    public function test_thumbnail_returns_404_when_outputs_has_no_thumbnail_key(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
            'outputs' => [],
        ]);

        $this->actingAs($user)->get("/media/{$media->uuid}/thumbnail")->assertNotFound();
    }

    public function test_thumbnail_returns_404_when_file_is_missing_from_disk(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
            'outputs' => ['thumbnail' => 'outputs/missing_thumbnail.jpg'],
        ]);
        // File intentionally not put on disk

        $this->actingAs($user)->get("/media/{$media->uuid}/thumbnail")->assertNotFound();
    }

    public function test_other_user_cannot_fetch_thumbnail(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $owner->id,
            'outputs' => ['thumbnail' => 'outputs/uuid_thumbnail.jpg'],
        ]);

        Storage::disk('media')->put('outputs/uuid_thumbnail.jpg', 'fake-image-bytes');

        $this->actingAs($other)->get("/media/{$media->uuid}/thumbnail")->assertForbidden();
    }
}
