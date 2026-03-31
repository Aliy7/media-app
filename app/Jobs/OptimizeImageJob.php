<?php

namespace App\Jobs;

use App\Events\MediaProcessingCompleted;
use App\Events\MediaProcessingFailed;
use App\Events\MediaStepCompleted;
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

    /** Horizon dashboard title — shows the original filename. */
    public function displayName(): string
    {
        return 'OptimizeImageJob [' . $this->media->original_filename . ']';
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
     * Delegate optimisation to ImageProcessingService, merge the output path
     * with previous step outputs, mark the record as completed, and fire
     * MediaProcessingCompleted.
     *
     * ImageProcessingService is resolved from the container by Laravel.
     */
    /** Progress reported when the optimising step starts — before marking complete. */
    private const STEP_PROGRESS = 90;

    public function handle(ImageProcessingService $service): void
    {
        // Pause so the previous step (thumbnail) stays visible in the UI.
        if ($delay = (int) config('media.step_delay', 0)) {
            sleep($delay);
        }

        $outputPath = $service->optimize(
            $this->media->stored_filename,
            'media',
        );

        $outputs              = $this->media->outputs ?? [];
        $outputs['optimized'] = $outputPath;

        // Write the optimising step while keeping status as processing so the
        // polling fallback also shows "Optimising…" before marking complete.
        $this->media->update([
            'processing_step' => 'optimize',
            'progress'        => self::STEP_PROGRESS,
            'outputs'         => $outputs,
        ]);

        $this->media->increment('bytes_processed', $this->media->file_size);

        // Broadcast the step so the UI transitions to "Optimising… 90%".
        event(new MediaStepCompleted($this->media, 'optimize', self::STEP_PROGRESS, $outputPath));

        // Hold "Optimising…" visible before firing the completion event.
        if ($delay = (int) config('media.step_delay', 0)) {
            sleep($delay);
        }

        $this->media->update([
            'status'   => Media::STATUS_COMPLETED,
            'progress' => 100,
        ]);

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
