<?php

namespace App\Events;

use App\Models\Media;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the failed() method on any job in the pipeline when all retry
 * attempts are exhausted. Carries the step that failed and the error message
 * so the UI can surface a meaningful failure reason.
 */
class MediaProcessingFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Media  $media,
        public readonly string $step,
        public readonly string $errorMessage,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('media.' . $this->media->uuid);
    }

    public function broadcastAs(): string
    {
        return 'media.processing.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'step'      => $this->step,
            'error'     => $this->errorMessage,
            'failed_at' => now()->toIso8601String(),
        ];
    }
}
