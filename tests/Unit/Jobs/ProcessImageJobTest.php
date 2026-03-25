<?php

namespace Tests\Unit\Jobs;

use App\Events\MediaProcessingFailed;
use App\Events\MediaProcessingStarted;
use App\Jobs\GenerateThumbnailJob;
use App\Jobs\OptimizeImageJob;
use App\Jobs\ProcessImageJob;
use App\Jobs\ResizeImageJob;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProcessImageJobTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // handle()
    // -----------------------------------------------------------------------

    public function test_handle_updates_media_status_to_processing(): void
    {
        Bus::fake();
        Event::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_PENDING]);

        (new ProcessImageJob($media))->handle();

        $this->assertEquals(Media::STATUS_PROCESSING, $media->fresh()->status);
    }

    public function test_handle_fires_media_processing_started_event(): void
    {
        Bus::fake();
        Event::fake();

        $media = Media::factory()->create();

        (new ProcessImageJob($media))->handle();

        Event::assertDispatched(MediaProcessingStarted::class, function ($event) use ($media) {
            return $event->media->is($media);
        });
    }

    public function test_handle_dispatches_resize_and_thumbnail_as_parallel_batch(): void
    {
        Bus::fake();
        Event::fake();

        $media = Media::factory()->create();

        (new ProcessImageJob($media))->handle();

        Bus::assertBatched(function (\Illuminate\Bus\PendingBatch $batch) {
            return $batch->jobs->count() === 2
                && $batch->jobs->contains(fn ($job) => $job instanceof ResizeImageJob)
                && $batch->jobs->contains(fn ($job) => $job instanceof GenerateThumbnailJob);
        });
    }

    // -----------------------------------------------------------------------
    // failed()
    // -----------------------------------------------------------------------

    public function test_failed_updates_media_status_to_failed(): void
    {
        Event::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);

        (new ProcessImageJob($media))->failed(new \RuntimeException('Dispatch error'));

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
    }

    public function test_failed_stores_error_message_on_media(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new ProcessImageJob($media))->failed(new \RuntimeException('Something went wrong'));

        $this->assertEquals('Something went wrong', $media->fresh()->error_message);
    }

    public function test_failed_fires_media_processing_failed_event(): void
    {
        Event::fake();

        $media = Media::factory()->create();

        (new ProcessImageJob($media))->failed(new \RuntimeException('Dispatch error'));

        Event::assertDispatched(MediaProcessingFailed::class, function ($event) use ($media) {
            return $event->media->is($media)
                && $event->step === 'dispatch'
                && $event->errorMessage === 'Dispatch error';
        });
    }
}
