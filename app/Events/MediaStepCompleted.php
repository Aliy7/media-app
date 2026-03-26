<?php

namespace App\Events;

use App\Models\Media;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after each intermediate processing step (resize, thumbnail).
 * Carries the step name, current progress percentage, and the output
 * path so the UI can advance the progress bar and show partial results.
 */
class MediaStepCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Media  $media,
        public readonly string $step,
        public readonly int    $progress,
        public readonly string $outputPath,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('media.' . $this->media->uuid);
    }

    public function broadcastAs(): string
    {
        return 'media.step.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'step'        => $this->step,
            'progress'    => $this->progress,
            'output_path' => $this->outputPath,
        ];
    }
}
