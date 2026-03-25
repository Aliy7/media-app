<?php

namespace App\Events;

use App\Models\Media;

/**
 * Fired after each intermediate processing step (resize, thumbnail).
 * Carries the step name, current progress percentage, and the output path
 * produced by that step so the UI can update progressively.
 * Phase 4 will implement ShouldBroadcast.
 */
class MediaStepCompleted
{
    public function __construct(
        public readonly Media  $media,
        public readonly string $step,
        public readonly int    $progress,
        public readonly string $outputPath,
    ) {}
}
