<?php

namespace App\Jobs;

use App\Events\MediaProcessingCompleted;
use App\Events\MediaProcessingFailed;
use App\Models\Media;
use App\Services\ImageProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Final step in the processing chain: optimises the uploaded image and
 * transitions the Media record to "completed" at 100 % progress.
 *
 * Queue: media-low (per spec — optimization is background work, lower urgency
 * than thumbnail generation which drives the UI preview)
 */
class OptimizeImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Maximum attempts before the job is marked as failed */
    public int $tries = 3;

    /** @var array<int> Seconds to wait before each retry (exponential back-off) */
    public array $backoff = [10, 30, 60];

    /** @var int Seconds before the job is considered timed out */
    public int $timeout = 120;

    public function __construct(private readonly Media $media)
    {
        // Optimisation is background polish — lower priority than thumbnail
        $this->onQueue('media-low');
    }

    /**
     * Delegate optimisation to ImageProcessingService, merge the output path
     * with previous step outputs, mark the record as completed, and fire
     * MediaProcessingCompleted.
     *
     * ImageProcessingService is resolved from the container by Laravel.
     */
    public function handle(ImageProcessingService $service): void
    {
        $outputPath = $service->optimize(
            $this->media->stored_filename,
            'media',
        );

        $outputs               = $this->media->outputs ?? [];
        $outputs['optimized']  = $outputPath;

        $this->media->update([
            'processing_step' => 'optimize',
            'status'          => Media::STATUS_COMPLETED,
            'progress'        => 100,
            'outputs'         => $outputs,
        ]);

        $this->media->increment('bytes_processed', $this->media->file_size);

        event(new MediaProcessingCompleted($this->media));
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

        event(new MediaProcessingFailed($this->media, 'optimize', $exception->getMessage()));
    }
}
