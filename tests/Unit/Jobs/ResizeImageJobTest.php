<?php

namespace Tests\Unit\Jobs;

use App\Events\MediaProcessingFailed;
use App\Events\MediaStepCompleted;
use App\Jobs\ResizeImageJob;
use App\Models\Media;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ResizeImageJobTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // handle()
    // -----------------------------------------------------------------------

    public function test_handle_calls_resize_on_image_processing_service(): void
    {
        Event::fake();

        $media   = Media::factory()->create(['stored_filename' => 'test.jpg']);
        $service = $this->createMock(ImageProcessingService::class);

        $service->expects($this->once())
            ->method('resize')
            ->with('test.jpg', 1920, 1080, 'media')
            ->willReturn('outputs/test_resized.jpg');

        (new ResizeImageJob($media, 1920, 1080))->handle($service);
    }

    public function test_handle_sets_processing_step_to_resize(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('resize')->willReturn('outputs/test_resized.jpg');

        (new ResizeImageJob($media))->handle($service);

        $this->assertEquals('resize', $media->fresh()->processing_step);
    }

    public function test_handle_sets_progress_to_33(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('resize')->willReturn('outputs/test_resized.jpg');

        (new ResizeImageJob($media))->handle($service);

        $this->assertEquals(33, $media->fresh()->progress);
    }

    public function test_handle_stores_output_path_in_media_outputs(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('resize')->willReturn('outputs/test_resized.jpg');

        (new ResizeImageJob($media))->handle($service);

        $this->assertEquals('outputs/test_resized.jpg', $media->fresh()->outputs['resized']);
    }

    public function test_handle_fires_media_step_completed_event(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('resize')->willReturn('outputs/test_resized.jpg');

        (new ResizeImageJob($media))->handle($service);

        Event::assertDispatched(MediaStepCompleted::class, function ($event) use ($media) {
            return $event->media->is($media)
                && $event->step === 'resize'
                && $event->progress === 33
                && $event->outputPath === 'outputs/test_resized.jpg';
        });
    }

    // -----------------------------------------------------------------------
    // failed()
    // -----------------------------------------------------------------------

    public function test_failed_updates_media_status_to_failed(): void
    {
        Event::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);

        (new ResizeImageJob($media))->failed(new \RuntimeException('Imagick error'));

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
    }

    public function test_failed_stores_error_message_on_media(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new ResizeImageJob($media))->failed(new \RuntimeException('Imagick error'));

        $this->assertEquals('Imagick error', $media->fresh()->error_message);
    }

    public function test_failed_fires_media_processing_failed_event(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new ResizeImageJob($media))->failed(new \RuntimeException('Imagick error'));

        Event::assertDispatched(MediaProcessingFailed::class, function ($event) use ($media) {
            return $event->media->is($media)
                && $event->step === 'resize'
                && $event->errorMessage === 'Imagick error';
        });
    }
}
