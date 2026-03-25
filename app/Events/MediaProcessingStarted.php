<?php

namespace App\Events;

use App\Models\Media;

/**
 * Fired by ProcessImageJob when a media record begins processing.
 * Phase 4 will implement ShouldBroadcast to push this to the UI.
 */
class MediaProcessingStarted
{
    public function __construct(public readonly Media $media) {}
}
