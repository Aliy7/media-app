<?php

namespace App\Events;

use App\Models\Media;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by ProcessImageJob when a media record begins processing.
 * Broadcast on the owner's private media channel so the UI can
 * transition from "pending" to "processing" in real time.
 */
class MediaProcessingStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Media $media) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('media.' . $this->media->uuid);
    }

    public function broadcastAs(): string
    {
        return 'media.processing.started';
    }

    public function broadcastWith(): array
    {
        return [
            'status'     => $this->media->status,
            'filename'   => $this->media->original_filename,
            'started_at' => now()->toIso8601String(),
        ];
    }
}
