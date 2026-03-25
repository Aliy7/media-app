<?php

namespace App\Events;

use App\Models\Media;

/**
 * Fired by OptimizeImageJob when all processing steps finish successfully.
 * Carries the full outputs map so the UI can render all output files at once.
 * Phase 4 will implement ShouldBroadcast.
 */
class MediaProcessingCompleted
{
    public function __construct(public readonly Media $media) {}
}
