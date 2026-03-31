<?php

namespace App\Jobs;

use App\Events\MediaProcessingFailed;
use App\Events\MediaStepCompleted;
use App\Models\Media;
use App\Services\ImageProcessingService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generates a square thumbnail from the uploaded image and stores the output
 * path on the Media record. Reports 66 % progress on completion.
 *
 * Queue: media-critical (per spec — thumbnails are needed first for UI previews)
 */
class GenerateThumbnailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Maximum attempts before the job is marked as failed */
    public int $tries = 3;

    /** @var array<int> Seconds to wait before each retry (exponential back-off) */
    public array $backoff = [10, 30, 60];

    /** @var int Seconds before the job is considered timed out */
    public int $timeout = 120;

    /** Progress percentage reported after this step completes */
    private const PROGRESS = 66;

    public function __construct(
        private readonly Media $media,
        private readonly int   $width  = 0,
        private readonly int   $height = 0,
    ) {
        // Thumbnails run on the critical queue — UI previews depend on them
        $this->onQueue('media-critical');
    }

    /** Horizon dashboard title — shows the original filename. */
    public function displayName(): string
    {
        return 'GenerateThumbnailJob [' . $this->media->original_filename . ']';
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
     * Delegate thumbnail generation to ImageProcessingService, persist the
     * output path alongside any previous step outputs, advance progress,
     * and fire MediaStepCompleted.
     *
     * Dimensions default to config('media.thumbnail.width/height') when not
     * explicitly provided at dispatch time.
     *
     * ImageProcessingService is resolved from the container by Laravel.
     */
    public function handle(ImageProcessingService $service): void
    {
        if ($delay = (int) config('media.step_delay', 0)) {
            sleep($delay);
        }

        $width  = $this->width  ?: (int) config('media.thumbnail.width',  300);
        $height = $this->height ?: (int) config('media.thumbnail.height', 300);

        $outputPath = $service->thumbnail(
            $this->media->stored_filename,
            $width,
            $height,
            'media',
        );

        $outputs               = $this->media->outputs ?? [];
        $outputs['thumbnail']  = $outputPath;

        $this->media->update([
            'processing_step' => 'thumbnail',
            'progress'        => self::PROGRESS,
            'outputs'         => $outputs,
        ]);

        $this->media->increment('bytes_processed', $this->media->file_size);

        event(new MediaStepCompleted($this->media, 'thumbnail', self::PROGRESS, $outputPath));
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

        event(new MediaProcessingFailed($this->media, 'thumbnail', $exception->getMessage()));
    }
}
