<?php

namespace App\Events;

use App\Models\Media;

/**
 * Fired by the failed() method on any job in the pipeline when all retry
 * attempts are exhausted. Carries the step that failed and the error message
 * so the UI can surface a meaningful failure reason.
 * Phase 4 will implement ShouldBroadcast.
 */
class MediaProcessingFailed
{
    public function __construct(
        public readonly Media  $media,
        public readonly string $step,
        public readonly string $errorMessage,
    ) {}
}
