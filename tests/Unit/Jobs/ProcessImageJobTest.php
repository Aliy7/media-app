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

    // -----------------------------------------------------------------------
    // displayName() and tags()
    // -----------------------------------------------------------------------

    public function test_display_name_includes_original_filename(): void
    {
        $media = Media::factory()->create(['original_filename' => 'holiday.jpg']);

        $name = (new ProcessImageJob($media))->displayName();

        $this->assertStringContainsString('holiday.jpg', $name);
        $this->assertStringContainsString('ProcessImageJob', $name);
    }

    public function test_tags_include_filename_and_uuid(): void
    {
        $media = Media::factory()->create(['original_filename' => 'holiday.jpg']);
        $tags  = (new ProcessImageJob($media))->tags();

        $this->assertTrue(collect($tags)->contains(fn ($t) => str_contains($t, 'holiday.jpg')));
        $this->assertTrue(collect($tags)->contains(fn ($t) => str_contains($t, $media->uuid)));
        $this->assertTrue(collect($tags)->contains(fn ($t) => str_starts_with($t, 'uploaded:')));
    }

    // -----------------------------------------------------------------------
    // Batch catch callback — fires when a parallel batch job fails terminally
    // -----------------------------------------------------------------------

    public function test_batch_catch_marks_media_failed_when_a_batch_job_fails(): void
    {
        Event::fake();

        $media = Media::factory()->create(['status' => Media::STATUS_PROCESSING]);

        // Capture the PendingBatch (with its then/catch callbacks) without actually
        // dispatching to a real queue or batch-repository DB table.
        $capturedBatch = null;

        Bus::shouldReceive('batch')
            ->once()
            ->andReturnUsing(function (array $jobs) use (&$capturedBatch) {
                $capturedBatch = new \Illuminate\Bus\PendingBatch(app(), collect($jobs));

                return new class ($capturedBatch) {
                    public function __construct(private \Illuminate\Bus\PendingBatch $inner) {}
                    public function then(callable $cb): static  { $this->inner->then($cb);  return $this; }
                    public function catch(callable $cb): static { $this->inner->catch($cb); return $this; }
                    public function dispatch(): mixed           { return null; }
                };
            });

        (new ProcessImageJob($media))->handle();

        // Invoke the catch callback directly to simulate a batch worker failure
        $exception = new \RuntimeException('Worker crashed');
        $batchMock = \Mockery::mock(\Illuminate\Bus\Batch::class);

        foreach ($capturedBatch->catchCallbacks() as $callback) {
            $callback($batchMock, $exception);
        }

        $this->assertEquals(Media::STATUS_FAILED, $media->fresh()->status);
        $this->assertEquals('Worker crashed', $media->fresh()->error_message);

        Event::assertDispatched(MediaProcessingFailed::class, fn ($e) =>
            $e->media->is($media) && $e->step === 'batch' && $e->errorMessage === 'Worker crashed'
        );
    }
}
