<?php

namespace App\Events;

use App\Models\Media;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by OptimizeImageJob when all processing steps finish successfully.
 * Payload exposes only the two display variants the UI needs: thumbnail
 * (grid/list preview) and optimised (full-size view). The resized intermediate
 * is internal to the pipeline and excluded.
 */
class MediaProcessingCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Media $media) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('media.' . $this->media->uuid);
    }

    public function broadcastAs(): string
    {
        return 'media.processing.completed';
    }

    public function broadcastWith(): array
    {
        $outputs = $this->media->outputs ?? [];

        return [
            'status'       => $this->media->status,
            'outputs'      => [
                'thumbnail' => $outputs['thumbnail'] ?? null,
                'optimized' => $outputs['optimized'] ?? null,
            ],
            'completed_at' => now()->toIso8601String(),
        ];
    }
}
