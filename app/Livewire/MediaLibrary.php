<?php

namespace App\Livewire;

use App\Models\Media;
use App\Services\MediaUploadService;
use Carbon\Carbon;
use Livewire\Component;

class MediaLibrary extends Component
{
    /**
     * In-memory overrides for status/progress received via WebSocket before
     * the next full re-render. Keyed by media UUID.
     *
     * @var array<string, array{status: string, progress: int, step: string|null, error: string|null}>
     */
    public array $liveUpdates = [];

    /** asc | desc — persisted in Livewire state across re-renders */
    public string $sortOrder = 'desc';

    /** all | pending | processing | completed | failed */
    public string $statusFilter = 'all';

    /**
     * Called from Alpine + Echo in the blade when a broadcast event arrives.
     * Triggers a Livewire re-render so updated values are applied immediately.
     */
    public function handleMediaUpdate(
        string  $uuid,
        string  $status,
        int     $progress,
        ?string $step,
        ?string $error,
    ): void {
        $this->liveUpdates[$uuid] = compact('status', 'progress', 'step', 'error');
    }

    /**
     * Re-queues a failed media card from the library without re-upload.
     * Ownership is verified — only the authenticated owner can retry their media.
     */
    public function retryMedia(string $uuid): void
    {
        $media = Media::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->where('status', Media::STATUS_FAILED)
            ->first();

        if (! $media) {
            return;
        }

        app(MediaUploadService::class)->retry($media);

        // Clear any stale live-update state for this card so it re-renders
        // from the fresh DB values immediately.
        unset($this->liveUpdates[$uuid]);
    }

    public function toggleSort(): void
    {
        $this->sortOrder = $this->sortOrder === 'desc' ? 'asc' : 'desc';
    }

    public function render()
    {
        $query = Media::where('user_id', auth()->id());

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $mediaItems = $query->orderBy('created_at', $this->sortOrder)->get();

        // Group into human-readable date buckets while preserving sort order.
        $grouped = $mediaItems->groupBy(fn ($m) => $this->dateBucket($m->created_at));

        return view('livewire.media-library', compact('grouped'))
            ->layout('layouts.app');
    }

    private function dateBucket(Carbon $date): string
    {
        if ($date->isToday())             return 'Today';
        if ($date->isYesterday())         return 'Yesterday';
        if ($date->gt(now()->subDays(7))) return 'This week';
        if ($date->gt(now()->subDays(30))) return 'This month';
        return $date->format('F Y');
    }
}
