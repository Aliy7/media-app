<?php

namespace Tests\Unit\Events;

use App\Events\MediaProcessingCompleted;
use App\Events\MediaProcessingFailed;
use App\Events\MediaProcessingStarted;
use App\Events\MediaStepCompleted;
use App\Models\Media;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaEventsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // MediaProcessingStarted
    // -------------------------------------------------------------------------

    public function test_media_processing_started_implements_should_broadcast(): void
    {
        $event = new MediaProcessingStarted(Media::factory()->make());

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_media_processing_started_broadcasts_on_private_media_channel(): void
    {
        $media = Media::factory()->make(['uuid' => 'test-uuid-1234']);

        $channel = (new MediaProcessingStarted($media))->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-media.test-uuid-1234', $channel->name);
    }

    public function test_media_processing_started_broadcast_name(): void
    {
        $event = new MediaProcessingStarted(Media::factory()->make());

        $this->assertSame('media.processing.started', $event->broadcastAs());
    }

    public function test_media_processing_started_payload_keys(): void
    {
        $media   = Media::factory()->make(['status' => 'processing', 'original_filename' => 'photo.jpg']);
        $payload = (new MediaProcessingStarted($media))->broadcastWith();

        $this->assertArrayHasKey('status', $payload);
        $this->assertArrayHasKey('filename', $payload);
        $this->assertArrayHasKey('started_at', $payload);
    }

    public function test_media_processing_started_payload_values(): void
    {
        $media   = Media::factory()->make(['status' => 'processing', 'original_filename' => 'photo.jpg']);
        $payload = (new MediaProcessingStarted($media))->broadcastWith();

        $this->assertSame('processing', $payload['status']);
        $this->assertSame('photo.jpg', $payload['filename']);
    }

    // -------------------------------------------------------------------------
    // MediaStepCompleted
    // -------------------------------------------------------------------------

    public function test_media_step_completed_implements_should_broadcast(): void
    {
        $event = new MediaStepCompleted(Media::factory()->make(), 'resize', 33, 'outputs/file_resized.jpg');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_media_step_completed_broadcasts_on_private_media_channel(): void
    {
        $media   = Media::factory()->make(['uuid' => 'test-uuid-1234']);
        $channel = (new MediaStepCompleted($media, 'resize', 33, 'outputs/file.jpg'))->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-media.test-uuid-1234', $channel->name);
    }

    public function test_media_step_completed_broadcast_name(): void
    {
        $event = new MediaStepCompleted(Media::factory()->make(), 'resize', 33, 'outputs/file.jpg');

        $this->assertSame('media.step.completed', $event->broadcastAs());
    }

    public function test_media_step_completed_payload_keys(): void
    {
        $payload = (new MediaStepCompleted(Media::factory()->make(), 'resize', 33, 'outputs/file.jpg'))
            ->broadcastWith();

        $this->assertArrayHasKey('step', $payload);
        $this->assertArrayHasKey('progress', $payload);
        $this->assertArrayHasKey('output_path', $payload);
    }

    public function test_media_step_completed_payload_values(): void
    {
        $payload = (new MediaStepCompleted(Media::factory()->make(), 'thumbnail', 66, 'outputs/thumb.jpg'))
            ->broadcastWith();

        $this->assertSame('thumbnail', $payload['step']);
        $this->assertSame(66, $payload['progress']);
        $this->assertSame('outputs/thumb.jpg', $payload['output_path']);
    }

    // -------------------------------------------------------------------------
    // MediaProcessingCompleted
    // -------------------------------------------------------------------------

    public function test_media_processing_completed_implements_should_broadcast(): void
    {
        $event = new MediaProcessingCompleted(Media::factory()->make());

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_media_processing_completed_broadcasts_on_private_media_channel(): void
    {
        $media   = Media::factory()->make(['uuid' => 'test-uuid-1234']);
        $channel = (new MediaProcessingCompleted($media))->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-media.test-uuid-1234', $channel->name);
    }

    public function test_media_processing_completed_broadcast_name(): void
    {
        $event = new MediaProcessingCompleted(Media::factory()->make());

        $this->assertSame('media.processing.completed', $event->broadcastAs());
    }

    public function test_media_processing_completed_payload_keys(): void
    {
        $payload = (new MediaProcessingCompleted(Media::factory()->make()))->broadcastWith();

        $this->assertArrayHasKey('status', $payload);
        $this->assertArrayHasKey('outputs', $payload);
        $this->assertArrayHasKey('completed_at', $payload);
    }

    public function test_media_processing_completed_outputs_contains_only_display_variants(): void
    {
        $media   = Media::factory()->make([
            'status'  => 'completed',
            'outputs' => [
                'resized'   => 'outputs/r.jpg',
                'thumbnail' => 'outputs/t.jpg',
                'optimized' => 'outputs/o.jpg',
            ],
        ]);
        $outputs = (new MediaProcessingCompleted($media))->broadcastWith()['outputs'];

        $this->assertArrayHasKey('thumbnail', $outputs);
        $this->assertArrayHasKey('optimized', $outputs);
        $this->assertArrayNotHasKey('resized', $outputs);
    }

    public function test_media_processing_completed_payload_values(): void
    {
        $media   = Media::factory()->make([
            'status'  => 'completed',
            'outputs' => ['thumbnail' => 'outputs/t.jpg', 'optimized' => 'outputs/o.jpg'],
        ]);
        $payload = (new MediaProcessingCompleted($media))->broadcastWith();

        $this->assertSame('completed', $payload['status']);
        $this->assertSame('outputs/t.jpg', $payload['outputs']['thumbnail']);
        $this->assertSame('outputs/o.jpg', $payload['outputs']['optimized']);
    }

    // -------------------------------------------------------------------------
    // MediaProcessingFailed
    // -------------------------------------------------------------------------

    public function test_media_processing_failed_implements_should_broadcast(): void
    {
        $event = new MediaProcessingFailed(Media::factory()->make(), 'resize', 'Disk full');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_media_processing_failed_broadcasts_on_private_media_channel(): void
    {
        $media   = Media::factory()->make(['uuid' => 'test-uuid-1234']);
        $channel = (new MediaProcessingFailed($media, 'resize', 'error'))->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-media.test-uuid-1234', $channel->name);
    }

    public function test_media_processing_failed_broadcast_name(): void
    {
        $event = new MediaProcessingFailed(Media::factory()->make(), 'resize', 'error');

        $this->assertSame('media.processing.failed', $event->broadcastAs());
    }

    public function test_media_processing_failed_payload_keys(): void
    {
        $payload = (new MediaProcessingFailed(Media::factory()->make(), 'resize', 'Disk full'))
            ->broadcastWith();

        $this->assertArrayHasKey('step', $payload);
        $this->assertArrayHasKey('error', $payload);
        $this->assertArrayHasKey('failed_at', $payload);
    }

    public function test_media_processing_failed_payload_values(): void
    {
        $payload = (new MediaProcessingFailed(Media::factory()->make(), 'optimize', 'Out of memory'))
            ->broadcastWith();

        $this->assertSame('optimize', $payload['step']);
        $this->assertSame('Out of memory', $payload['error']);
    }
}
