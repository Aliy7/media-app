<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_belongs_to_user(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $media->user);
        $this->assertEquals($user->id, $media->user->id);
    }

    public function test_outputs_cast_to_array(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id' => $user->id,
            'outputs' => [['path' => 'media/thumb.jpg', 'width' => 150, 'height' => 150]],
        ]);

        $this->assertIsArray($media->fresh()->outputs);
        $this->assertEquals('media/thumb.jpg', $media->fresh()->outputs[0]['path']);
    }

    public function test_progress_cast_to_integer(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id, 'progress' => 50]);

        $this->assertIsInt($media->fresh()->progress);
        $this->assertEquals(50, $media->fresh()->progress);
    }

    public function test_status_constants_defined(): void
    {
        $this->assertEquals('pending',    Media::STATUS_PENDING);
        $this->assertEquals('processing', Media::STATUS_PROCESSING);
        $this->assertEquals('completed',  Media::STATUS_COMPLETED);
        $this->assertEquals('failed',     Media::STATUS_FAILED);
    }

    public function test_default_status_is_pending(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);

        $this->assertEquals(Media::STATUS_PENDING, $media->status);
    }
}
