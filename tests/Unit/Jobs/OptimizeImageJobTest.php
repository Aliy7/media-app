<?php

namespace Tests\Unit\Jobs;

use App\Events\MediaProcessingCompleted;
use App\Events\MediaProcessingFailed;
use App\Jobs\OptimizeImageJob;
use App\Models\Media;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OptimizeImageJobTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // handle()
    // -----------------------------------------------------------------------

    public function test_handle_calls_optimize_on_image_processing_service(): void
    {
        Event::fake();

        $media   = Media::factory()->create(['stored_filename' => 'test.jpg']);
        $service = $this->createMock(ImageProcessingService::class);

        $service->expects($this->once())
            ->method('optimize')
            ->with('test.jpg', 'media')
            ->willReturn('outputs/test_optimized.jpg');

        (new OptimizeImageJob($media))->handle($service);
    }

    public function test_handle_sets_processing_step_to_optimize(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('optimize')->willReturn('outputs/test_optimized.jpg');

        (new OptimizeImageJob($media))->handle($service);

        $this->assertEquals('optimize', $media->fresh()->processing_step);
    }

    public function test_handle_updates_status_to_completed(): void
    {
        Event::fake();

        $media   = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('optimize')->willReturn('outputs/test_optimized.jpg');

        (new OptimizeImageJob($media))->handle($service);

        $this->assertEquals(Media::STATUS_COMPLETED, $media->fresh()->status);
    }

    public function test_handle_sets_progress_to_100(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('optimize')->willReturn('outputs/test_optimized.jpg');

        (new OptimizeImageJob($media))->handle($service);

        $this->assertEquals(100, $media->fresh()->progress);
    }

    public function test_handle_stores_output_path_in_media_outputs(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('optimize')->willReturn('outputs/test_optimized.jpg');

        (new OptimizeImageJob($media))->handle($service);

        $this->assertEquals('outputs/test_optimized.jpg', $media->fresh()->outputs['optimized']);
    }

    public function test_handle_preserves_existing_outputs_when_adding_optimized(): void
    {
        Event::fake();

        $existing = ['resized' => 'outputs/r.jpg', 'thumbnail' => 'outputs/t.jpg'];
        $media    = Media::factory()->create(['outputs' => $existing]);
        $service  = $this->createMock(ImageProcessingService::class);
        $service->method('optimize')->willReturn('outputs/test_optimized.jpg');

        (new OptimizeImageJob($media))->handle($service);

        $outputs = $media->fresh()->outputs;
        $this->assertArrayHasKey('resized', $outputs);
        $this->assertArrayHasKey('thumbnail', $outputs);
        $this->assertArrayHasKey('optimized', $outputs);
    }

    public function test_handle_fires_media_processing_completed_event(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('optimize')->willReturn('outputs/test_optimized.jpg');

        (new OptimizeImageJob($media))->handle($service);

        Event::assertDispatched(MediaProcessingCompleted::class, function ($event) use ($media) {
            return $event->media->is($media);
        });
    }

    // -----------------------------------------------------------------------
    // failed()
    // -----------------------------------------------------------------------

    public function test_failed_updates_media_status_to_failed(): void
    {
        Event::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);

        (new OptimizeImageJob($media))->failed(new \RuntimeException('Optimize error'));

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
    }

    public function test_failed_stores_error_message_on_media(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new OptimizeImageJob($media))->failed(new \RuntimeException('Optimize error'));

        $this->assertEquals('Optimize error', $media->fresh()->error_message);
    }

    public function test_failed_fires_media_processing_failed_event(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new OptimizeImageJob($media))->failed(new \RuntimeException('Optimize error'));

        Event::assertDispatched(MediaProcessingFailed::class, function ($event) use ($media) {
            return $event->media->is($media)
                && $event->step === 'optimize'
                && $event->errorMessage === 'Optimize error';
        });
    }

    // -----------------------------------------------------------------------
    // failed() — edge cases
    // -----------------------------------------------------------------------

    /** Service throws during handle() — exception must propagate so Laravel retries the job */
    public function test_handle_propagates_exception_when_service_throws(): void
    {
        Event::fake();

        $media   = Media::factory()->create();
        $service = $this->createMock(ImageProcessingService::class);
        $service->method('optimize')->willThrowException(new \RuntimeException('disk full'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disk full');

        (new OptimizeImageJob($media))->handle($service);
    }

    /** failed() is idempotent — calling it on already-failed media overwrites cleanly */
    public function test_failed_is_idempotent_on_already_failed_media(): void
    {
        Event::fake();

        $media = Media::factory()->create([
            'status'        => Media::STATUS_FAILED,
            'error_message' => 'previous error',
        ]);

        (new OptimizeImageJob($media))->failed(new \RuntimeException('new error'));

        $fresh = $media->fresh();
        $this->assertEquals(Media::STATUS_FAILED, $fresh->status);
        $this->assertEquals('new error', $fresh->error_message);
    }

    /** failed() preserves previously written outputs — prior steps' work must not be lost */
    public function test_failed_does_not_wipe_existing_outputs(): void
    {
        Event::fake();

        $media = Media::factory()->create([
            'outputs' => ['resized' => 'outputs/r.jpg', 'thumbnail' => 'outputs/t.jpg'],
        ]);

        (new OptimizeImageJob($media))->failed(new \RuntimeException('boom'));

        $this->assertArrayHasKey('resized',    $media->fresh()->outputs);
        $this->assertArrayHasKey('thumbnail',  $media->fresh()->outputs);
    }

    /** Non-RuntimeException types (e.g. LogicException) are handled correctly */
    public function test_failed_handles_non_runtime_exception_types(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new OptimizeImageJob($media))->failed(new \LogicException('logic fault'));

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
        $this->assertEquals('logic fault', $media->fresh()->error_message);
    }

    /** Exception message is preserved verbatim — no truncation or wrapping */
    public function test_failed_preserves_full_exception_message(): void
    {
        Event::fake();

        $longMessage = str_repeat('x', 500);
        $media       = Media::factory()->create();

        (new OptimizeImageJob($media))->failed(new \RuntimeException($longMessage));

        $this->assertEquals($longMessage, $media->fresh()->error_message);
    }

    /** No MediaProcessingCompleted event is fired when the job fails */
    public function test_failed_does_not_fire_processing_completed_event(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new OptimizeImageJob($media))->failed(new \RuntimeException('fail'));

        Event::assertNotDispatched(MediaProcessingCompleted::class);
    }

    // -----------------------------------------------------------------------
    // displayName() and tags()
    // -----------------------------------------------------------------------

    public function test_display_name_includes_original_filename(): void
    {
        $media = Media::factory()->create(['original_filename' => 'photo.jpg']);

        $name = (new OptimizeImageJob($media))->displayName();

        $this->assertStringContainsString('photo.jpg', $name);
        $this->assertStringContainsString('OptimizeImageJob', $name);
    }

    public function test_tags_include_filename_and_uuid(): void
    {
        $media = Media::factory()->create(['original_filename' => 'photo.jpg']);
        $tags  = (new OptimizeImageJob($media))->tags();

        $this->assertTrue(collect($tags)->contains(fn ($t) => str_contains($t, 'photo.jpg')));
        $this->assertTrue(collect($tags)->contains(fn ($t) => str_contains($t, $media->uuid)));
        $this->assertTrue(collect($tags)->contains(fn ($t) => str_starts_with($t, 'uploaded:')));
    }
}
