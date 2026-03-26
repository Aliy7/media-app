<?php

namespace App\Jobs;

use App\Events\MediaProcessingFailed;
use App\Events\MediaProcessingStarted;
use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

/**
 * Orchestrator job: transitions a Media record to "processing", fires the
 * MediaProcessingStarted event, then dispatches the three-step chain:
 *   ResizeImageJob → GenerateThumbnailJob → OptimizeImageJob
 *
 * This job itself does no image I/O. Its sole responsibility is coordination.
 */
class ProcessImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Maximum attempts before the job is marked as failed */
    public int $tries = 3;

    /** @var array<int> Seconds to wait before each retry (exponential back-off) */
    public array $backoff = [10, 30, 60];

    /** @var int Seconds before the job is considered timed out */
    public int $timeout = 120;

    public function __construct(
        public Media $media,
        private readonly int   $width           = 1920,
        private readonly int   $height          = 1080,
        private readonly int   $thumbnailWidth  = 0,
        private readonly int   $thumbnailHeight = 0,
    ) {
        // Orchestration runs on standard priority
        $this->onQueue('media-standard');
    }

    /** Horizon dashboard title — shows the original filename. */
    public function displayName(): string
    {
        return 'ProcessImageJob [' . $this->media->original_filename . ']';
    }

    /**
     * Horizon searchable tags — filename, upload age, and UUID.
     * `diffForHumans()` produces strings like "2 hours ago", "3 weeks ago".
     */
    public function tags(): array
    {
        return [
            'file:'     . $this->media->original_filename,
            'uploaded:' . $this->media->created_at->diffForHumans(),
            'uuid:'     . $this->media->uuid,
        ];
    }

    /**
     * Update status, fire start event, then dispatch resize and thumbnail in
     * parallel via a batch. Once both complete, the batch's `then` callback
     * dispatches OptimizeImageJob as the final step.
     */
    public function handle(): void
    {
        $this->media->update(['status' => Media::STATUS_PROCESSING]);

        event(new MediaProcessingStarted($this->media));

        $media = $this->media;

        Bus::batch([
            (new ResizeImageJob($media, $this->width, $this->height))->onQueue('media-standard'),
            (new GenerateThumbnailJob($media, $this->thumbnailWidth, $this->thumbnailHeight))->onQueue('media-critical'),
        ])
        ->then(fn (Batch $batch) => OptimizeImageJob::dispatch($media))
        ->catch(function (Batch $batch, \Throwable $e) use ($media) {
            $media->update([
                'status'        => Media::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
            event(new MediaProcessingFailed($media, 'batch', $e->getMessage()));
        })
        ->dispatch();
    }

    /**
     * Called by Laravel after all retry attempts are exhausted.
     * Marks the media record as failed and notifies listeners.
     */
    public function failed(\Throwable $exception): void
    {
        $this->media->update([
            'status'        => Media::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
        ]);

        event(new MediaProcessingFailed($this->media, 'dispatch', $exception->getMessage()));
    }
}
