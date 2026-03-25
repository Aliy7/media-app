<?php

namespace Tests\Unit\Jobs;

use App\Events\MediaProcessingFailed;
use App\Events\MediaStepCompleted;
use App\Jobs\GenerateThumbnailJob;
use App\Models\Media;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GenerateThumbnailJobTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // handle()
    // -----------------------------------------------------------------------

    public function test_handle_calls_thumbnail_on_image_processing_service(): void
    {
        Event::fake();

        $media   = Media::factory()->create(['stored_filename' => 'test.jpg']);
        $service = $this->createMock(ImageProcessingService::class);

        $service->expects($this->once())
            ->method('thumbnail')
            ->with('test.jpg', 300, 300, 'media')
            ->willReturn('outputs/test_thumbnail.jpg');

        (new GenerateThumbnailJob($media, 300, 300))->handle($service);
    }

    public function test_handle_sets_processing_step_to_thumbnail(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('thumbnail')->willReturn('outputs/test_thumbnail.jpg');

        (new GenerateThumbnailJob($media))->handle($service);

        $this->assertEquals('thumbnail', $media->fresh()->processing_step);
    }

    public function test_handle_sets_progress_to_66(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('thumbnail')->willReturn('outputs/test_thumbnail.jpg');

        (new GenerateThumbnailJob($media))->handle($service);

        $this->assertEquals(66, $media->fresh()->progress);
    }

    public function test_handle_stores_output_path_in_media_outputs(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('thumbnail')->willReturn('outputs/test_thumbnail.jpg');

        (new GenerateThumbnailJob($media))->handle($service);

        $this->assertEquals('outputs/test_thumbnail.jpg', $media->fresh()->outputs['thumbnail']);
    }

    public function test_handle_preserves_existing_outputs_when_adding_thumbnail(): void
    {
        Event::fake();

        $media   = Media::factory()->create(['outputs' => ['resized' => 'outputs/test_resized.jpg']]);
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('thumbnail')->willReturn('outputs/test_thumbnail.jpg');

        (new GenerateThumbnailJob($media))->handle($service);

        $outputs = $media->fresh()->outputs;
        $this->assertArrayHasKey('resized', $outputs);
        $this->assertArrayHasKey('thumbnail', $outputs);
    }

    public function test_handle_fires_media_step_completed_event(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('thumbnail')->willReturn('outputs/test_thumbnail.jpg');

        (new GenerateThumbnailJob($media))->handle($service);

        Event::assertDispatched(MediaStepCompleted::class, function ($event) use ($media) {
            return $event->media->is($media)
                && $event->step === 'thumbnail'
                && $event->progress === 66
                && $event->outputPath === 'outputs/test_thumbnail.jpg';
        });
    }

    // -----------------------------------------------------------------------
    // failed()
    // -----------------------------------------------------------------------

    public function test_failed_updates_media_status_to_failed(): void
    {
        Event::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);

        (new GenerateThumbnailJob($media))->failed(new \RuntimeException('Thumbnail error'));

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
    }

    public function test_failed_stores_error_message_on_media(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new GenerateThumbnailJob($media))->failed(new \RuntimeException('Thumbnail error'));

        $this->assertEquals('Thumbnail error', $media->fresh()->error_message);
    }

    public function test_failed_fires_media_processing_failed_event(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new GenerateThumbnailJob($media))->failed(new \RuntimeException('Thumbnail error'));

        Event::assertDispatched(MediaProcessingFailed::class, function ($event) use ($media) {
            return $event->media->is($media)
                && $event->step === 'thumbnail'
                && $event->errorMessage === 'Thumbnail error';
        });
    }
}
